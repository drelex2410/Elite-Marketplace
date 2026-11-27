@extends('seller.layouts.app')

@section('title', translate('Uploaded Files'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h3 mb-1">{{ translate('Uploaded Files') }}</h1><p class="text-muted mb-0">{{ translate('Only files owned by your seller account are shown.') }}</p></div>
        <a class="btn btn-warning" href="{{ route('seller.uploaded_files.create') }}">{{ translate('Upload File') }}</a>
    </div>
    <div class="seller-card">
        <form method="GET" class="mb-3"><input class="form-control" name="search" value="{{ $search }}" placeholder="{{ translate('Search files') }}"></form>
        <div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('File') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Size') }}</th><th></th></tr></thead><tbody>
        @forelse ($all_uploads as $file)
            <tr><td>{{ $file->file_original_name }}</td><td>{{ $file->extension }}</td><td>{{ number_format(((int) $file->file_size) / 1024, 1) }} KB</td><td class="text-right"><form method="POST" action="{{ route('seller.uploaded-files.destroy', $file) }}" onsubmit="return confirm('{{ translate('Are you sure to delete this?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">{{ translate('Delete') }}</button></form></td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-5">{{ translate('No uploaded files found.') }}</td></tr>
        @endforelse
        </tbody></table></div>
        {{ $all_uploads->links() }}
    </div>
@endsection
