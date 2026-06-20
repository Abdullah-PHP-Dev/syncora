@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="col-xxl-12 mb-0">
    <div class="d-flex justify-content-end mb-3">
        <a href="{{route('admin.ads.campaigns.create', ['platform' => $platform])}}">
            <button class="btn btn-primary btn-sm">
                <i class="icon-plus bx bx-plus"></i>
                {{__('admin.marketing_tools.ads.campaign.header')}}
            </button>
        </a>
    </div>
    <div class="card">

        <div class="card-datatable table-responsive">
            <table class="invoice-list-table table table-border-top-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{__('admin.marketing_tools.ads.campaign.name')}}</th>
                    <th>{{__('admin.marketing_tools.ads.campaign.objective')}}</th>
                    <th>{{__('admin.marketing_tools.ads.campaign.status')}}</th>
                    <th>{{__('admin.marketing_tools.ads.campaign.start_time')}}</th>
                    <th>{{__('admin.marketing_tools.ads.campaign.end_time')}}</th>
                    <th class="cell-fit">{{__('admin.marketing_tools.ads.campaign.action')}}</th>
                </tr>
                </thead>
                <tbody id="apiTable" class="table-border-bottom-0">
                    @foreach ($campaigns as $campaign)
                    <tr data-key="{{$campaign->key}}">
                        <td>{{$campaign->campaign_id}}</td>
                        <td>{{$campaign->name}}</td>
                        <td>{{$campaign->objective}}</td>
                        <td><span class="badge bg-label-success"> {{$campaign->status}} </span></td>
                        <td>{{ \Carbon\Carbon::parse($campaign->start_time)->format('d M Y, h:i A') }}</td>
                        <td>{{ \Carbon\Carbon::parse($campaign->end_time)->format('d M Y, h:i A') }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="dropdown">
                                    <a href="javascript:;" class="btn dropdown-toggle hide-arrow p-0" data-bs-toggle="dropdown"><i class="icon-base bx bx-dots-vertical-rounded icon-md text-body"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="javascript:void(0);" data-key="{{$campaign->key}}" class="dropdown-item edit">{{__('admin.table.edit')}}</a>
                                        
                                        <div class="dropdown-divider"></div>
                                        <a href="javascript:;" data-key="{{$campaign->key}}" class="dropdown-item delete-record text-danger">{{__('admin.table.delete')}}</a>
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
<div class="modal fade" id="apiModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg social-modal">

            <div class="modal-header border-0 pb-0 mt-0 pt-0">
                <div>
                    <h4 class="mb-1 font-weight-bold mb-0 mt-0">{{__('admin.api.create_header')}}</h4>
                    <small class="text-muted">{{__('admin.api.create_description')}}</small>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body pt-4">
                <div class="row">

                    <form id="api" method="POST">
                        @csrf
                        <input type="hidden" name="id" id="api_id">
                        <input type="hidden" name="mode" id="form_mode" value="create">
                        </div>
                        <!-- Key -->
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.api.key') }}<span class="error-message">*</span></label>
                            <input type="text" id="key" name="key" class="form-control" value="">
                            <p class="error-message error-key"></p>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.api.value') }}<span class="error-message">*</span></label>
                            <input type="text" id="value" name="value" class="form-control"  value="">
                            <p class="error-message error-value"></p>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            {{ __('admin.api.create_api') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        var areYouSure = "{{__('admin.sweet-alert.are-you-sure')}}";
        var YouWontBeAbleToRevertThis = "{{__('admin.sweet-alert.you-wont-be-able-to-revert-this')}}";
        var YesDeleteIt = "{{__('admin.sweet-alert.yes-delete-it')}}";
        var recordHasBeenDelete = "{{__('admin.sweet-alert.record-has-been-deleted')}}";
        var deleted = "{{__('admin.sweet-alert.deleted')}}";
        var saveDescription = "{{__('admin.sweet-alert.save-description')}}";
        var saveHeader = "{{__('admin.sweet-alert.save-header')}}";
        var saveHeader = "{{__('admin.sweet-alert.save-header')}}";
        var dontSave = "{{__('admin.sweet-alert.dont-save')}}";
        var wentWrong = "{{__('admin.sweet-alert.went-wrong')}}";
        var error = "{{__('admin.sweet-alert.error')}}";
        var success = "{{__('admin.sweet-alert.success')}}";
        var changesNotSaved = "{{__('admin.sweet-alert.changes-not-saved')}}";
        var apiUrl = "{{ route('admin.apis.store') }}";
        var getAPIUrl = "{{ route('admin.apis.show', ['api' => ':API']) }}";
        var updateAPIUrl = "{{ route('admin.apis.update', ['api' => ':API']) }}";
        var destroyAPIUrl = "{{ route('admin.apis.destroy', ['api' => ':API']) }}";
        var edit = "{{__('admin.table.edit')}}";
        var deletebutton = "{{__('admin.table.delete')}}";
    </script>

    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush