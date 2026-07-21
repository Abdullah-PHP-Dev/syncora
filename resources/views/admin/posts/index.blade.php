@extends('layouts.app')

@section('page_title')
    {{ __('View web') }}
@stop

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .media-preview-wrapper {
            width: 140px;
            height: 90px;
            overflow: hidden;
            border-radius: 8px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dee2e6;
        }

        .media-preview-wrapper img,
        .media-preview-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .media-preview-wrapper iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        .table td, .table th {
            vertical-align: middle !important;
        }
    </style>
@endpush

@section('content')

<div class="container py-4">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            
            {{-- Header Section --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold mb-0 text-dark">
                    {{ __('admin.marketing_tools.posts.' . $platform . '.header') }}
                </h4>
            </div>

            <hr class="my-4 text-muted">

            {{-- Posts Action Header --}}
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h5 class="fw-bold mb-0">
                    {{ __('admin.marketing_tools.posts.header') }}
                </h5>
                <a href="{{ route('admin.posts.create', ['platform' => $platform ?? request('platform'), 'locale' => app()->getLocale()]) }}" 
                   class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-plus me-1"></i>
                    {{ __('admin.marketing_tools.posts.post-create-header') }}
                </a>
            </div>

            {{-- Posts Table Section --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            @php
                                $headings = [
                                    '#',
                                    __('admin.marketing_tools.posts.created-date'),
                                    __('admin.marketing_tools.posts.status'),
                                    __('admin.marketing_tools.posts.content'),
                                    __('admin.marketing_tools.posts.scheduled-date'),
                                    __('admin.marketing_tools.posts.media'),
                                    __('admin.marketing_tools.posts.action'),
                                ];
                            @endphp
                            @foreach ($headings as $heading)
                                <th scope="col">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($posts as $_eachPost)
                            <tr data-id="{{ $_eachPost->id }}">
                                <td class="fw-semibold">{{ $_eachPost->id }}</td>
                                <td>{{ optional($_eachPost?->created_at)->format('M d, Y h:i A') ?? '-' }}</td>
                                <td>
                                    @if ($_eachPost->status === 'completed')
                                        <span class="badge bg-success">{{ __('Published') }}</span>
                                    @elseif($_eachPost->status === 'failed')
                                        <span class="badge bg-danger">{{ __('Failed') }}</span>
                                    @elseif($_eachPost->schedule_mode == 1 && \Carbon\Carbon::parse($_eachPost->schedule_at)->isFuture())
                                        <span class="badge bg-warning text-dark">{{ __('Scheduled') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($_eachPost->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" title="{{ $_eachPost->content }}">
                                        {{ $_eachPost->content }}
                                    </div>
                                </td>
                                <td>{{ optional($_eachPost?->schedule_at)->format('M d, Y h:i A') ?? '-' }}</td>
                                <td>
                                    @php
                                        // Get the collection of media or create a fallback array
                                        $mediaItems = $_eachPost->media;
                                        $firstMedia = $mediaItems->first();
                                        $mediaUrl = $firstMedia?->media_url ?? $_eachPost->url ?? null;
                                        $totalCount = $mediaItems->count();
                                        
                                        $extension = pathinfo($mediaUrl, PATHINFO_EXTENSION);
                                        $images = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
                                        $videos = ['mp4', 'webm', 'ogg'];
                                    @endphp
                                    
                                    <div class="position-relative media-preview-wrapper">
                                        @if ($mediaUrl && in_array(strtolower($extension), $images))
                                            <img src="{{ $mediaUrl }}" alt="media">
                                        @elseif ($mediaUrl && in_array(strtolower($extension), $videos))
                                            <video controls preload="metadata">
                                                <source src="{{ $mediaUrl }}" type="video/{{ strtolower($extension) }}">
                                                Your browser does not support the video tag.
                                            </video>
                                        @elseif ($_eachPost->platform == 'youtube' && $mediaUrl)
                                            @php
                                                parse_str(parse_url($mediaUrl, PHP_URL_QUERY), $query);
                                                $videoId = $query['v'] ?? null;
                                            @endphp
                                    
                                            @if ($videoId)
                                                <iframe src="https://www.youtube.com/embed/{{ $videoId }}" allowfullscreen></iframe>
                                            @else
                                                <a href="{{ $mediaUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-external-link-alt"></i> View Media
                                                </a>
                                            @endif
                                        @elseif ($mediaUrl)
                                            <a href="{{ $mediaUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-external-link-alt"></i> View Media
                                            </a>
                                        @else
                                            <span class="text-muted small">No Media</span>
                                        @endif
                                    
                                        {{-- Show total count if more than 1 media file exists --}}
                                        @if ($totalCount > 1)
                                            <span class="position-absolute top-0 end-0 badge rounded-pill bg-dark bg-opacity-75 m-1">
                                                +{{ $totalCount - 1 }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.posts.show', ['post' => $_eachPost->id, 'platform' => strtolower($platform ?? request()->platform)]) }}"
                                           class="btn btn-outline-primary btn-sm" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <a href="#" 
                                           data-id="{{ $_eachPost->id }}"
                                           data-url="{{ route('admin.posts.destroy', ['post' => $_eachPost->id, 'platform' => strtolower($platform ?? request()->platform)]) }}"
                                           class="btn btn-outline-danger btn-sm delete-entity" 
                                           data-type="media-campaigns"
                                           title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    {{ __('No posts found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Laravel Pagination Bar --}}
            @if ($posts instanceof \Illuminate\Pagination\AbstractPaginator && $posts->hasPages())
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <div class="text-muted small">
                        Showing {{ $posts->firstItem() }} to {{ $posts->lastItem() }} of {{ $posts->total() }} entries
                    </div>
                    <div>
                        {{ $posts->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            @endif

            <hr class="my-4 text-muted">

            {{-- Footer Action --}}
            <div class="mt-3">
                <a href="{{ route('admin.posts.dashboard') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}
                </a>
            </div>

        </div>
    </div>
</div>

@stop

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById('timeSavedChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Content created', 'Published', 'Analytics', 'Engage'],
                        datasets: [{
                            data: [23.5, 8.5, 4, 2.5],
                            backgroundColor: ['#45B6D8', '#29D9A9', '#FF99F0', '#5B7FFF'],
                            borderWidth: 0,
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        cutout: '70%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': ' + context.raw + 'h';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });

        $(document).on('click', '.delete-entity', function(e) {
            e.preventDefault();

            var url = $(this).data('url');
            var entityId = $(this).data('id');

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this post!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        method: "DELETE",
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.success) {
                                $(`tr[data-id="${entityId}"]`).fadeOut(300, function() {
                                    $(this).remove();
                                });

                                Swal.fire(
                                    'Deleted!',
                                    response.message || 'The post has been deleted successfully.',
                                    'success'
                                );
                            } else {
                                Swal.fire(
                                    'Error!',
                                    response.error || response.message || response.data || 'Something went wrong.',
                                    'error'
                                );
                            }
                        },
                        error: function(xhr) {
                            let response = xhr.responseJSON;
                            Swal.fire(
                                'Error!',
                                response?.error || response?.message || response?.data || 'Something went wrong.',
                                'error'
                            );
                        }
                    });
                }
            });
        });
    </script>
@endpush