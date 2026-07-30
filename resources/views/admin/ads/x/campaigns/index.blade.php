@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="col-xxl-12 mb-0">
        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('admin.ads.campaigns.create', ['platform' => $platform]) }}">
                <button class="btn btn-primary btn-sm">
                    <i class="icon-plus bx bx-plus"></i>
                    {{ __('admin.marketing_tools.ads.campaign.header') }}
                </button>
            </a>
        </div>
        <div class="card">

            <div class="card-datatable table-responsive">
                <table class="invoice-list-table table table-border-top-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('admin.marketing_tools.ads.campaign.name') }}</th>
                            <th>{{ __('admin.marketing_tools.ads.campaign.status') }}</th>
                            <th>{{ __('admin.marketing_tools.ads.campaign.start_time') }}</th>
                            <th>{{ __('admin.marketing_tools.ads.campaign.end_time') }}</th>
                            <th class="cell-fit">{{ __('admin.marketing_tools.ads.campaign.action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="apiTable" class="table-border-bottom-0">
                        @foreach ($campaigns as $campaign)
                            <tr data-key="{{ $campaign->key }}">
                                <td>{{ $campaign->id }}</td>
                                <td>{{ $campaign->name }}</td>
                                <td><span class="badge status-badge {{ $campaign->status ? 'bg-label-success' : 'bg-label-secondary' }}"> {{ $campaign->status ? 'Active' : 'Paused' }} </span></td>
                                <td>{{ \Carbon\Carbon::parse($campaign->start_time)->format('d M Y') }}</td>
                                <td>{{ \Carbon\Carbon::parse($campaign->end_time)->format('d M Y') }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="dropdown">
                                            <a href="javascript:;" class="btn dropdown-toggle hide-arrow p-0"
                                                data-bs-toggle="dropdown"><i
                                                    class="icon-base bx bx-dots-vertical-rounded icon-md text-body"></i></a>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a href="{{ route('admin.ads.campaigns.edit', ['platform' => $platform, 'campaign' => $campaign->id]) }}" data-key="{{ $campaign->key }}"
                                                    class="dropdown-item">{{ __('admin.table.edit') }}</a>

                                                <a href="javascript:;" data-key="{{ $campaign->id }}"
                                                    data-status="{{ $campaign->status ? 'PAUSED' : 'ACTIVE' }}"
                                                    class="dropdown-item status-toggle-record">{{ $campaign->status ? 'Pause' : 'Activate' }}</a>

                                                <div class="dropdown-divider"></div>
                                                <a href="javascript:;" data-key="{{ $campaign->id }}"
                                                    class="dropdown-item delete-record text-danger">{{ __('admin.table.delete') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        var areYouSure = "{{ __('admin.sweet-alert.are-you-sure') }}";
        var YouWontBeAbleToRevertThis = "{{ __('admin.sweet-alert.you-wont-be-able-to-revert-this') }}";
        var YesDeleteIt = "{{ __('admin.sweet-alert.yes-delete-it') }}";
        var recordHasBeenDelete = "{{ __('admin.sweet-alert.record-has-been-deleted') }}";
        var deleted = "{{ __('admin.sweet-alert.deleted') }}";
        var saveDescription = "{{ __('admin.sweet-alert.save-description') }}";
        var saveHeader = "{{ __('admin.sweet-alert.save-header') }}";
        var dontSave = "{{ __('admin.sweet-alert.dont-save') }}";
        var wentWrong = "{{ __('admin.sweet-alert.went-wrong') }}";
        var error = "{{ __('admin.sweet-alert.error') }}";
        var success = "{{ __('admin.sweet-alert.success') }}";
        var changesNotSaved = "{{ __('admin.sweet-alert.changes-not-saved') }}";
        var destroyAPIUrl = "{{ route('admin.ads.campaigns.destroy', ['platform' => 'x', 'campaign' => ':API']) }}";
        var statusAPIUrl = "{{ route('admin.ads.campaigns.status', ['platform' => 'x', 'id' => ':API']) }}";
        var edit = "{{ __('admin.table.edit') }}";
        var deletebutton = "{{ __('admin.table.delete') }}";
    </script>

    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
