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



    <posts-dashboard
            :initial-posts='@json($posts->items())'
            :initial-total="{{ $posts->total() }}"
            :initial-last-page="{{ $posts->lastPage() }}"
            :initial-per-page="{{ $posts->perPage() }}"
            :platform-counts='@json($platformCounts)'
            platform="{{ $platform }}"
            create-url="{{ route('admin.posts.create', ['platform' => $platform]) }}"
            api-url="{{ route('admin.posts.data') }}"
            preview-url-base="{{ url('admin/posts') }}"
            user-name="{{ auth()->user()->name ?? 'Admin' }}"
    ></posts-dashboard>


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