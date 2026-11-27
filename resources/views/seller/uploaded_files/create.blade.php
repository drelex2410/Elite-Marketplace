@extends('seller.layouts.app')

@section('title', translate('Upload File'))

@section('content')
    <div class="mb-3"><h1 class="h3 mb-1">{{ translate('Upload File') }}</h1><p class="text-muted">{{ translate('Upload media for products, your shop, and verification documents.') }}</p></div>
    <div class="seller-card">
        <div class="input-group" data-toggle="aizuploader" data-multiple="true">
            <div class="input-group-prepend"><div class="input-group-text">{{ translate('Browse') }}</div></div>
            <div class="form-control file-amount">{{ translate('Choose files') }}</div>
            <input type="hidden" class="selected-files">
        </div>
        <div class="file-preview box sm mt-3"></div>
        <a class="btn btn-outline-secondary mt-3" href="{{ route('seller.uploaded_files') }}">{{ translate('Back to files') }}</a>
    </div>
@endsection
