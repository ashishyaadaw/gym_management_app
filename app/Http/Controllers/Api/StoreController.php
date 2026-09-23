<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsOnDateRange;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StoreSale;
use App\Models\StoreSaleItem;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Front-desk POS, sales history/reports and stock control for supplements, accessories and apparel. */
class StoreController extends Controller
{
    use ReportsOnDateRange;

    // ---------- Catalogue ----------

    public function products(Request $request)
    {
        // Only admins manage the catalogue, so only they see deactivated products.
        $query = Product::query()->orderBy('category')->orderBy('name');
        if (! ($request->user()->isAdmin() && $request->boolean('all'))) {
            $query->where('is_active', true);
        }

        return response()->json($query->get());
    }

    public function storeProduct(Request $request)
    {
        $product = Product::create($this->validated($request));

        if ($product->stock > 0) {
            $this->logMovement($product, $request, $product->stock, 'restock', 'Opening stock');
        }

        return response()->json($product, 201);
    }

    public function updateProduct(Request $request, Product $product)
    {
        // Stock is changed through adjustStock() so every change leaves a trail.
        $product->update(collect($this->validated($request))->except('stock')->all());

        return response()->json($product);
    }

    /** Add stock (restock), or correct it down (damaged / miscount). */
    public function adjustStock(Request $request, Product $product)
    {
        $data = $request->validate([
            'change' => 'required|integer|not_in:0|between:-100000,100000',
            'reason' => 'required|in:restock,adjustment,damaged',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($product, $data, $request) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->first();

            if ($locked->stock + $data['change'] < 0) {
                throw ValidationException::withMessages([
                    'change' => ["Stock can't go below zero — only {$locked->stock} in stock."],
                ]);
            }

            $locked->increment('stock', $data['change']);
            $this->logMovement($locked, $request, $data['change'], $data['reason'], $data['note'] ?? null);
        });

        return response()->json($product->fresh());
    }

    /** The last 15 stock changes for a product. */
    public function movements(Product $product)
    {
        return response()->json(
            StockMovement::with('user:id,name')->where('product_id', $product->id)->orderByDesc('id')->limit(15)->get()
        );
    }

    // ---------- Selling ----------

    /** Sell one or more products; stock is checked and decremented atomically. */
    public function sell(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'method' => 'required|in:cash,upi,card',
            'member_id' => 'nullable|exists:users,id',
        ]);

        // The same product could appear twice in the request; fold them together.
        $wanted = collect($data['items'])->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'));

        $sale = DB::transaction(function () use ($data, $wanted, $request) {
            $products = Product::whereIn('id', $wanted->keys())->where('is_active', true)->lockForUpdate()->get()->keyBy('id');

            $total = 0;
            foreach ($wanted as $productId => $qty) {
                $product = $products->get($productId);
                if (! $product) {
                    throw ValidationException::withMessages(['items' => ['A product in the cart is no longer available.']]);
                }
                if ($product->stock < $qty) {
                    throw ValidationException::withMessages(['items' => ["Only {$product->stock} left of {$product->name}."]]);
                }
                $total += $product->selling_price * $qty;
            }

            $sale = StoreSale::create([
                'sold_by' => $request->user()->id,
                'member_id' => $data['member_id'] ?? null,
                'total' => $total,
                'method' => $data['method'],
                'sold_at' => now(),
            ]);

            foreach ($wanted as $productId => $qty) {
                $product = $products->get($productId);
                StoreSaleItem::create([
                    'store_sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $product->selling_price,
                    'unit_cost' => $product->cost_price,
                ]);
                $product->decrement('stock', $qty);
                $this->logMovement($product, $request, -$qty, 'sale', $sale->receipt_number, $sale->id);
            }

            return $sale;
        });

        return response()->json($sale->load('items'), 201);
    }

    /** Cancel a mistaken sale: the stock goes back and it stops counting as revenue. Admin only. */
    public function void(Request $request, StoreSale $sale)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:255']);

        DB::transaction(function () use ($sale, $data, $request) {
            $locked = StoreSale::whereKey($sale->id)->lockForUpdate()->with('items')->first();

            if ($locked->isVoided()) {
                throw ValidationException::withMessages(['sale' => ['This sale has already been voided.']]);
            }

            foreach ($locked->items as $item) {
                if ($item->product_id && ($product = Product::find($item->product_id))) {
                    $product->increment('stock', $item->quantity);
                    $this->logMovement($product, $request, $item->quantity, 'void', 'Void '.$locked->receipt_number, $locked->id);
                }
            }

            $locked->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
                'void_reason' => $data['reason'] ?? null,
            ]);
        });

        return response()->json($sale->fresh()->load('items'));
    }

    // ---------- History & reports ----------

    /** Sales in a range (?from=&to=, default today), newest first, voided ones included and flagged. */
    public function sales(Request $request)
    {
        [$from, $to] = $this->range($request);

        return response()->json(
            StoreSale::with('items', 'seller:id,name', 'member:id,name')
                ->whereBetween('sold_at', [$from, $to])
                ->latest('sold_at')->latest('id')
                ->limit(500)
                ->get()
        );
    }

    /**
     * Totals for a range: revenue, units, average sale, split by payment mode, revenue per day and
     * best-sellers. Cost and profit are only sent to admins. Voided sales are reported separately.
     */
    public function report(Request $request)
    {
        [$from, $to] = $this->range($request);
        $isAdmin = $request->user()->isAdmin();

        $sales = StoreSale::with('items')->whereBetween('sold_at', [$from, $to])->get();
        $done = $sales->where('status', 'completed');
        $voided = $sales->where('status', 'voided');
        $items = $done->flatMap->items;

        $revenue = round((float) $done->sum('total'), 2);
        $cost = round((float) $items->sum(fn ($i) => $i->unit_cost * $i->quantity), 2);
        $byDay = $done->groupBy(fn ($s) => $s->sold_at->toDateString());

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sales_count' => $done->count(),
            'units' => (int) $items->sum('quantity'),
            'revenue' => $revenue,
            'average_sale' => $done->count() ? round($revenue / $done->count(), 2) : 0,
            'cost' => $isAdmin ? $cost : null,
            'profit' => $isAdmin ? round($revenue - $cost, 2) : null,
            'voided_count' => $voided->count(),
            'voided_amount' => round((float) $voided->sum('total'), 2),
            'by_method' => collect(['cash', 'upi', 'card'])->mapWithKeys(
                fn ($m) => [$m => round((float) $done->where('method', $m)->sum('total'), 2)]
            ),
            'by_day' => collect(CarbonPeriod::create($from, $to))->map(function (Carbon $d) use ($byDay) {
                $day = $byDay->get($d->toDateString(), collect());

                return ['date' => $d->toDateString(), 'label' => $d->format('d M'), 'total' => round((float) $day->sum('total'), 2), 'count' => $day->count()];
            })->values(),
            'top_products' => $items->groupBy('name')->map(fn ($rows, $name) => [
                'name' => $name,
                'quantity' => (int) $rows->sum('quantity'),
                'revenue' => round((float) $rows->sum(fn ($i) => $i->unit_price * $i->quantity), 2),
                'profit' => $isAdmin ? round((float) $rows->sum(fn ($i) => ($i->unit_price - $i->unit_cost) * $i->quantity), 2) : null,
            ])->sortByDesc('revenue')->take(8)->values(),
        ]);
    }

    /** Download the sales in a range as a CSV (opens in Excel). */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $isAdmin = $request->user()->isAdmin();

        $sales = StoreSale::with('items', 'seller:id,name', 'member:id,name')
            ->whereBetween('sold_at', [$from, $to])
            ->orderBy('sold_at')->orderBy('id')
            ->get();

        $filename = 'store-sales-'.$from->toDateString().'_to_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($sales, $isAdmin) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly

            fputcsv($out, array_merge(
                ['Receipt', 'Date', 'Time', 'Status', 'Items', 'Units', 'Member', 'Payment', 'Total'],
                $isAdmin ? ['Cost', 'Profit'] : [],
                ['Sold by', 'Void reason'],
            ));

            foreach ($sales as $s) {
                $cost = $s->items->sum(fn ($i) => $i->unit_cost * $i->quantity);
                fputcsv($out, array_merge([
                    $s->receipt_number,
                    $s->sold_at->format('Y-m-d'),
                    $s->sold_at->format('H:i'),
                    $s->status,
                    $this->csvSafe($s->items->map(fn ($i) => "{$i->name} x{$i->quantity}")->implode('; ')),
                    $s->items->sum('quantity'),
                    $this->csvSafe($s->member?->name ?? 'Walk-in'),
                    $s->method,
                    $s->total,
                ], $isAdmin ? [number_format($cost, 2, '.', ''), number_format((float) $s->total - $cost, 2, '.', '')] : [], [
                    $this->csvSafe($s->seller?->name ?? ''),
                    $this->csvSafe($s->void_reason ?? ''),
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------- Helpers (range() and csvSafe() come from ReportsOnDateRange) ----------

    private function logMovement(Product $product, Request $request, int $change, string $reason, ?string $note = null, ?int $saleId = null): void
    {
        StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'store_sale_id' => $saleId,
            'change' => $change,
            'reason' => $reason,
            'note' => $note,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:60',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);
    }
}
