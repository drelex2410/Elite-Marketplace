<?php

namespace App\Http\Controllers;

use App\Models\SellerPackagePayment;
use App\Services\Sellers\SellerPackageActivationService;
use Illuminate\Http\Request;

class SellerPackagePaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    public function offline_payment_request()
    {
        $package_payment_requests = SellerPackagePayment::where('offline_payment', 1)->orderBy('id', 'desc')->paginate(10);

        return view('backend.manual_payment_methods.seller_package_payment_request', compact('package_payment_requests'));
    }

    public function offline_payment_approval($id, SellerPackageActivationService $activationService)
    {
        $user = auth()->user();
        abort_unless($user && ($user->user_type === 'admin' || $user->can('approve_offline_seller_package_payment')), 403);
        $packagePayment = SellerPackagePayment::query()->findOrFail($id);
        $activationService->approveOffline($packagePayment, $user);
        flash(translate('Offline Seller Package Payment approved successfully.'))->success();

        return back();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
