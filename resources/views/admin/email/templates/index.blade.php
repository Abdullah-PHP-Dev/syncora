@extends('layouts.app')

@section('title', __('email_marketing.templates.title'))

@push('styles')
@include('layouts.partials.dash-styles')
<style>
    .socialeaz-dash .category-pill {
        display: inline-flex; align-items: center; padding: .4rem .9rem; border-radius: .6rem; font-size: .8125rem;
        font-weight: 600; text-decoration: none; border: 1px solid var(--dash-border); color: var(--dash-text); white-space: nowrap;
    }
    .socialeaz-dash .category-pill.is-active { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; }
    .socialeaz-dash .category-pill:not(.is-active):hover { border-color: var(--dash-primary); color: var(--dash-primary); }
    .socialeaz-dash .view-toggle-btn { width: 34px; height: 34px; border-radius: .5rem; border: 1px solid var(--dash-border); background: var(--dash-card); color: var(--dash-muted); display: inline-flex; align-items: center; justify-content: center; }
    .socialeaz-dash .view-toggle-btn.active { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; }
    .socialeaz-dash .template-card { display: flex; flex-direction: column; height: 100%; }
    .socialeaz-dash .template-thumb { position: relative; height: 160px; border-radius: .7rem; overflow: hidden; border: 1px solid var(--dash-border); background: var(--dash-card-hover); margin-bottom: .9rem; }
    .socialeaz-dash .template-thumb iframe { width: 250%; height: 250%; transform: scale(.4); transform-origin: top left; border: none; pointer-events: none; background: #fff; }
    .socialeaz-dash .template-thumb .status-ribbon { position: absolute; top: .5rem; inset-inline-start: .5rem; z-index: 2; }
    .socialeaz-dash .template-tags { display: flex; gap: .4rem; flex-wrap: wrap; margin: .5rem 0; }
    .socialeaz-dash .template-card-footer { margin-top: auto; display: flex; align-items: center; justify-content: space-between; padding-top: .75rem; }
    #templatesListView.d-none, #templatesGridView.d-none { display: none !important; }
</style>
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="email-hero">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bx bx-file"></i> {{ __('email_marketing.templates.title') }}</h4>
                <p>{{ __('email_marketing.templates.subtitle') }}</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('admin.email.templates.create') }}" class="dash-btn dash-btn-primary"><i class="bx bx-plus"></i> {{ __('email_marketing.templates.new_template') }}</a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-9">

            <div class="dash-card mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ request()->fullUrlWithQuery(['category' => null]) }}" class="category-pill @if(!request('category')) is-active @endif">{{ __('email_marketing.templates.all_templates') }}</a>
                        @foreach ($categories as $category)
                            <a href="{{ request()->fullUrlWithQuery(['category' => $category]) }}" class="category-pill @if(request('category') === $category) is-active @endif">{{ $category }}</a>
                        @endforeach
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="view-toggle-btn active" id="gridViewBtn" title="{{ __('email_marketing.templates.grid_view') }}"><i class="bx bx-grid-alt"></i></button>
                        <button type="button" class="view-toggle-btn" id="listViewBtn" title="{{ __('email_marketing.templates.list_view') }}"><i class="bx bx-list-ul"></i></button>
                    </div>
                </div>

                <form method="GET" class="d-flex gap-2">
                    @if (request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                    <input type="text" name="search" class="dash-input flex-grow-1" placeholder="{{ __('email_marketing.templates.search_placeholder') }}" value="{{ request('search') }}">
                    <button type="submit" class="dash-btn dash-btn-ghost"><i class="bx bx-search"></i></button>
                </form>
            </div>

            <div id="templatesGridView" class="row g-3">
                @forelse ($templates as $template)
                    <div class="col-md-6 col-xl-4">
                        <div class="dash-card template-card">
                            <div class="template-thumb">
                                <span class="dash-badge dash-badge-{{ $template->status === 'published' ? 'success' : ($template->status === 'archived' ? 'muted' : 'warning') }} status-ribbon">{{ emailLabel('statuses', $template->status) }}</span>
                                <iframe srcdoc="{{ $template->body }}" tabindex="-1"></iframe>
                            </div>
                            <h6 style="color:var(--dash-heading);margin-bottom:.2rem;">{{ $template->name }}</h6>
                            <p class="dash-subtitle small mb-0 text-truncate">{{ $template->subject }}</p>
                            <div class="template-tags">
                                @if ($template->category)
                                    <span class="dash-badge dash-badge-info">{{ $template->category }}</span>
                                @endif
                            </div>
                            <div class="template-card-footer">
                                <small class="dash-subtitle">{{ __('email_marketing.templates.updated_ago', ['when' => $template->updated_at->diffForHumans()]) }}</small>
                                <div class="d-flex gap-2 align-items-center">
                                    <div class="dropdown">
                                        <a href="javascript:;" class="btn btn-sm p-0" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="{{ route('admin.email.templates.edit', $template) }}" class="dropdown-item">{{ __('email_marketing.common.edit') }}</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:;" class="dropdown-item text-danger" onclick="if (confirm(@js(__('email_marketing.templates.confirm_delete')))) { document.getElementById('delete-{{ $template->id }}').submit(); }">{{ __('email_marketing.common.delete') }}</a>
                                        </div>
                                    </div>
                                    <a href="{{ route('admin.email.campaigns.create', ['template' => $template->id]) }}" class="dash-btn dash-btn-primary">{{ __('email_marketing.templates.use_template') }}</a>
                                </div>
                            </div>
                            <form id="delete-{{ $template->id }}" action="{{ route('admin.email.templates.destroy', $template) }}" method="POST" class="d-none">@csrf @method('DELETE')</form>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="dash-card text-center py-5">
                            <i class="bx bx-file fs-1 d-block mb-2" style="color:var(--dash-muted);"></i>
                            <span class="dash-subtitle">{{ __('email_marketing.templates.empty') }} <a href="{{ route('admin.email.templates.create') }}" class="dash-link">{{ __('email_marketing.templates.empty_link') }}</a>.</span>
                        </div>
                    </div>
                @endforelse
            </div>

            <div id="templatesListView" class="dash-card d-none">
                <div class="table-responsive">
                    <table class="dash-table mb-0">
                        <thead>
                            <tr><th>{{ __('email_marketing.common.name') }}</th><th>{{ __('email_marketing.common.subject') }}</th><th>{{ __('email_marketing.templates.category') }}</th><th>{{ __('email_marketing.common.status') }}</th><th>{{ __('email_marketing.templates.updated') }}</th><th class="text-end">{{ __('email_marketing.common.action') }}</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($templates as $template)
                                <tr>
                                    <td>{{ $template->name }}</td>
                                    <td>{{ $template->subject }}</td>
                                    <td>{{ $template->category ?: '—' }}</td>
                                    <td><span class="dash-badge dash-badge-{{ $template->status === 'published' ? 'success' : ($template->status === 'archived' ? 'muted' : 'warning') }}">{{ emailLabel('statuses', $template->status) }}</span></td>
                                    <td>{{ $template->updated_at->diffForHumans() }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.email.campaigns.create', ['template' => $template->id]) }}" class="btn btn-sm btn-primary">{{ __('email_marketing.templates.use') }}</a>
                                        <a href="{{ route('admin.email.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">{{ __('email_marketing.common.edit') }}</a>
                                        <form action="{{ route('admin.email.templates.destroy', $template) }}" method="POST" class="d-inline" onsubmit="return confirm(@js(__('email_marketing.templates.confirm_delete')));">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('email_marketing.common.delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="dash-empty-row">{{ __('email_marketing.templates.empty_short') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($templates->hasPages())
                <div class="mt-3">{{ $templates->links() }}</div>
            @endif
        </div>

        <div class="col-lg-3">
            <div class="dash-card mb-3">
                <h6 class="mb-3" style="color:var(--dash-heading);"><i class="bx bx-filter-alt"></i> {{ __('email_marketing.templates.filter_title') }}</h6>
                <form method="GET">
                    <label class="form-label small">{{ __('email_marketing.templates.category') }}</label>
                    <select name="category" class="form-select form-select-sm mb-3 dash-input flex-grow-1" onchange="this.form.submit()">
                        <option value="">{{ __('email_marketing.templates.all_categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    <label class="form-label small">{{ __('email_marketing.templates.sort_by') }}</label>
                    <select name="sort" class="form-select form-select-sm dash-input flex-grow-1" onchange="this.form.submit()">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>{{ __('email_marketing.templates.sort_newest') }}</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('email_marketing.templates.sort_oldest') }}</option>
                        <option value="name" @selected(request('sort') === 'name')>{{ __('email_marketing.templates.sort_name') }}</option>
                    </select>
                    @if (request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                </form>
            </div>

            <div class="dash-card mb-3">
                <h6 class="mb-3" style="color:var(--dash-heading);"><i class="bx bx-bolt-circle"></i> {{ __('email_marketing.templates.quick_actions') }}</h6>
                <a href="{{ route('admin.email.templates.create') }}" class="dash-btn dash-btn-primary w-100 justify-content-center mb-2"><i class="bx bx-plus"></i> {{ __('email_marketing.templates.create_new') }}</a>
                <a href="{{ route('admin.email.templates.create') }}" class="dash-btn dash-btn-ghost w-100 justify-content-center"><i class="bx bx-bulb"></i> {{ __('email_marketing.templates.generate_ai') }}</a>
            </div>

            <div class="dash-card mb-3">
                <h6 class="mb-3" style="color:var(--dash-heading);"><i class="bx bx-bar-chart-alt-2"></i> {{ __('email_marketing.templates.stats') }}</h6>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="dash-stat-value" style="font-size:1.3rem;">{{ $totalTemplates }}</div>
                        <div class="dash-stat-label">{{ __('email_marketing.templates.stat_total') }}</div>
                    </div>
                    <div class="col-4">
                        <div class="dash-stat-value" style="font-size:1.3rem;">{{ $draftCount }}</div>
                        <div class="dash-stat-label">{{ __('email_marketing.statuses.draft') }}</div>
                    </div>
                    <div class="col-4">
                        <div class="dash-stat-value" style="font-size:1.3rem;">{{ $publishedCount }}</div>
                        <div class="dash-stat-label">{{ __('email_marketing.statuses.published') }}</div>
                    </div>
                </div>
            </div>

            <div class="dash-card" style="background:var(--dash-card-hover);box-shadow:none;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bx bx-bulb" style="color:var(--dash-primary);font-size:1.2rem;"></i>
                    <strong style="color:var(--dash-heading);font-size:.85rem;">{{ __('email_marketing.templates.pro_tip') }}</strong>
                </div>
                <p class="dash-subtitle small mb-2">{{ __('email_marketing.templates.pro_tip_text') }}</p>
                <a href="{{ route('admin.email.templates.create') }}" class="dash-link">{{ __('email_marketing.templates.try_ai') }} <i class="bx bx-right-arrow-alt"></i></a>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('load', function () {
        const gridView = document.getElementById('templatesGridView');
        const listView = document.getElementById('templatesListView');
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');

        function setView(view) {
            const isGrid = view === 'grid';
            gridView.classList.toggle('d-none', !isGrid);
            listView.classList.toggle('d-none', isGrid);
            gridBtn.classList.toggle('active', isGrid);
            listBtn.classList.toggle('active', !isGrid);
            try { localStorage.setItem('emailTemplatesView', view); } catch (e) {}
        }

        gridBtn.addEventListener('click', () => setView('grid'));
        listBtn.addEventListener('click', () => setView('list'));

        let savedView = 'grid';
        try { savedView = localStorage.getItem('emailTemplatesView') || 'grid'; } catch (e) {}
        setView(savedView);
    });
</script>
@endpush
