<?php

namespace App\Addons\Multivendor\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentInitializationService;
use Illuminate\Http\Request;

class SellerPackageController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gatewayManager) {}

    public function select_package(Request $request)
    {
        $shop = $request->user()->shop;
        $packages = SellerPackage::query()->where('status', true)->orderBy('display_order')->get();
        $gateways = $this->gatewayManager->availableForSellerPackages();

        return view('seller.packages.index', compact('shop', 'packages', 'gateways'));
    }

    public function package_purchase(Request $request, PaymentInitializationService $paymentService)
    {
        $validated = $request->validate([
            'seller_package_id' => ['required', 'integer', 'exists:seller_packages,id'],
            'gateway' => ['required', 'string', 'max:100'],
            'transactionId' => ['nullable', 'string', 'max:191'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $package = SellerPackage::query()->where('status', true)->findOrFail($validated['seller_package_id']);
        $request->merge([
            'payment_type' => 'seller_package_payment',
            'payment_method' => $validated['gateway'],
            'seller_package_id' => $package->id,
            'amount' => $package->amount,
            'currency' => $package->currency ?: config('app.currency', 'NGN'),
            'redirect_to' => route('seller.package_purchase_history'),
        ]);

        return $paymentService->initializeWeb($validated['gateway'], $request);
    }

    public function offline_seller_package_purchase_modal(Request $request)
    {
        $package = SellerPackage::query()->where('status', true)->findOrFail($request->integer('package_id'));
        $gateways = collect($this->gatewayManager->availableForSellerPackages())
            ->filter(fn (array $gateway) => str_starts_with($gateway['value'], 'offline_payment-'));

        return view('seller.packages.offline-modal', compact('package', 'gateways'));
    }

    public function package_purchase_history(Request $request)
    {
        $shop = $request->user()->shop;
        $payments = SellerPackagePayment::query()
            ->with('seller_package')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return view('seller.packages.history', compact('shop', 'payments'));
    }
}
