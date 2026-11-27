<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Enums\ProductReviewStatus;
use App\Http\Controllers\DigitalProductController as BaseDigitalProductController;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DigitalProductController extends BaseDigitalProductController
{
    public function store(Request $request)
    {
        $this->validatePayload($request);
        $before = Product::where('shop_id', auth()->user()->shop_id)->max('id');

        parent::store($request);

        Product::where('shop_id', auth()->user()->shop_id)
            ->where('digital', 1)
            ->where('id', '>', $before ?? 0)
            ->update([
                'published' => false,
                'approved' => false,
                'review_status' => ProductReviewStatus::PendingApproval->value,
                'submitted_for_review_at' => now(),
            ]);

        return redirect()->route('seller.digitalproducts.index');
    }

    public function edit($id)
    {
        $this->ownedProduct((int) $id);

        return parent::edit($id);
    }

    public function update(Request $request, $id)
    {
        $product = $this->ownedProduct((int) $id);
        $this->validatePayload($request);

        parent::update($request, $id);

        $product->forceFill([
            'published' => false,
            'approved' => false,
            'review_status' => ProductReviewStatus::PendingApproval->value,
            'submitted_for_review_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'review_notes' => null,
        ])->save();

        return redirect()->route('seller.digitalproducts.index');
    }

    public function destroy($id)
    {
        $this->ownedProduct((int) $id);
        parent::destroy($id);

        return redirect()->route('seller.digitalproducts.index');
    }

    public function download(Request $request)
    {
        $product = $this->ownedProduct((int) decrypt($request->id));
        $upload = Upload::whereKey($product->file_name)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->download($upload->file_name, $upload->file_original_name.'.'.$upload->extension);
        }

        abort_unless(is_file(public_path($upload->file_name)), 404);

        return response()->download(public_path($upload->file_name));
    }

    private function ownedProduct(int $id): Product
    {
        return Product::whereKey($id)
            ->where('shop_id', auth()->user()->shop_id)
            ->where('digital', 1)
            ->firstOrFail();
    }

    private function validatePayload(Request $request): void
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'file' => ['required', 'integer'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        Upload::whereKey($request->integer('file'))
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }
}
