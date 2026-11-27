<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $shop = $request->user()->shop;
        $this->authorize('view', $shop);

        return view('seller.shop.settings', compact('shop'));
    }

    public function create()
    {
        return redirect()->route('seller.shop.index');
    }

    public function store(): never
    {
        abort(405);
    }

    public function show(Request $request, int $id)
    {
        return $this->edit($request, $id);
    }

    public function edit(Request $request, int $id)
    {
        $shop = Shop::query()->ownedBy($request->user()->id)->findOrFail($id);
        $this->authorize('update', $shop);

        return view('seller.shop.settings', compact('shop'));
    }

    public function update(Request $request, int $id)
    {
        $shop = Shop::query()->ownedBy($request->user()->id)->findOrFail($id);
        $this->authorize('update', $shop);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'meta_title' => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'integer', 'exists:uploads,id'],
            'banners' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($validated['logo'])) {
            abort_unless(\App\Models\Upload::query()->where('id', $validated['logo'])->where('user_id', $request->user()->id)->exists(), 403);
        }
        $shop->forceFill($validated)->save();

        return back()->with('success', translate('Shop settings updated.'));
    }

    public function destroy(): never
    {
        abort(405);
    }

    public function verify_form(Request $request)
    {
        $shop = $request->user()->shop;

        return view('seller.shop.verification', compact('shop'));
    }

    public function verify_form_store(Request $request)
    {
        $documentIds = collect(explode(',', (string) $request->input('document_ids')))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->values()
            ->all();
        $request->merge(['document_upload_ids' => $documentIds]);
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:191'],
            'registration_number' => ['required', 'string', 'max:191'],
            'tax_number' => ['nullable', 'string', 'max:191'],
            'document_upload_ids' => ['required', 'array', 'min:1'],
            'document_upload_ids.*' => ['integer', 'exists:uploads,id'],
        ]);
        $ownedUploadCount = \App\Models\Upload::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['document_upload_ids'])
            ->count();
        abort_unless($ownedUploadCount === count(array_unique($validated['document_upload_ids'])), 403);

        $shop = $request->user()->shop;
        $shop->verification_info = json_encode($validated);
        $shop->verification_status = false;
        $shop->save();

        return redirect()->route('seller.dashboard')->with('success', translate('Verification request submitted.'));
    }
}
