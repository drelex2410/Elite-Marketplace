@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
        <div class="col-md-6"><h1 class="h3">{{ translate('Sellers') }}</h1></div>
        <div class="col-md-6 text-md-right"><a href="{{ route('admin.seller.create') }}" class="btn btn-primary"><i class="las la-plus"></i> {{ translate('Add New Seller') }}</a></div>
    </div>
</div>

<div class="row gutters-10 mb-3">
    @foreach (['pending', 'approved', 'active', 'inactive', 'suspended', 'rejected'] as $filterStatus)
        <div class="col-6 col-md-2 mb-2">
            <a class="card card-body text-center p-3 {{ $status === $filterStatus ? 'border-primary' : '' }}" href="{{ route('admin.all_sellers', ['status' => $filterStatus]) }}">
                <strong>{{ (int) ($statusCounts[$filterStatus] ?? 0) }}</strong><span class="text-muted">{{ translate(ucfirst($filterStatus)) }}</span>
            </a>
        </div>
    @endforeach
</div>

<div class="card">
    <form class="card-header row gutters-5 align-items-center" method="GET">
        <div class="col-md-4"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ translate('Search seller, shop, email or phone') }}" aria-label="{{ translate('Search sellers') }}"></div>
        <div class="col-md-3"><select name="status" class="form-control"><option value="">{{ translate('All statuses') }}</option>@foreach (['pending', 'approved', 'active', 'inactive', 'suspended', 'rejected'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ translate(ucfirst($option)) }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-primary" type="submit">{{ translate('Filter') }}</button></div>
    </form>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table aiz-table mb-0">
                <thead><tr><th>{{ translate('Seller') }}</th><th>{{ translate('Shop') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Verified') }}</th><th>{{ translate('Package') }}</th><th class="text-right">{{ translate('Actions') }}</th></tr></thead>
                <tbody>
                @forelse ($sellers as $seller)
                    @php $sellerStatus = $seller->shop?->status instanceof \App\Enums\ShopStatus ? $seller->shop->status->value : ($seller->shop?->status ?: 'pending'); @endphp
                    <tr>
                        <td><strong>{{ $seller->name }}</strong><br><small>{{ $seller->email }} · {{ $seller->phone }}</small></td>
                        <td>{{ $seller->shop?->name ?: translate('Missing shop') }}</td>
                        <td><span class="badge badge-inline badge-{{ $sellerStatus === 'active' ? 'success' : ($sellerStatus === 'suspended' ? 'danger' : 'warning') }}">{{ ucfirst($sellerStatus) }}</span></td>
                        <td>{{ $seller->shop?->verification_status ? translate('Yes') : translate('No') }}</td>
                        <td>{{ $seller->shop?->seller_package?->name ?: '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.seller.edit', $seller->id) }}" class="btn btn-soft-primary btn-icon btn-circle btn-sm" title="{{ translate('Edit') }}"><i class="las la-edit"></i></a>
                            @if ($seller->shop)
                                <a href="{{ route('admin.show_verification_request', $seller->shop->id) }}" class="btn btn-soft-info btn-icon btn-circle btn-sm" title="{{ translate('Review') }}"><i class="las la-eye"></i></a>
                                <form method="POST" action="{{ route('admin.seller.status', $seller->shop) }}" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $sellerStatus === 'active' ? 'inactive' : 'active' }}"><button class="btn btn-soft-{{ $sellerStatus === 'active' ? 'warning' : 'success' }} btn-icon btn-circle btn-sm" title="{{ $sellerStatus === 'active' ? translate('Deactivate') : translate('Activate') }}"><i class="las la-power-off"></i></button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5">{{ translate('No sellers found.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="aiz-pagination mt-3">{{ $sellers->links() }}</div>
    </div>
</div>
@endsection
