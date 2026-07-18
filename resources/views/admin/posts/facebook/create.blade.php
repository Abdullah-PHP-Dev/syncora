@extends('layouts.app')

@section('title', 'Create Facebook Post')

@section('content')
    <div class="container py-4">
        <div class="row">
            <!-- Main Composer Column -->
            <div class="col-lg-8">
                <div class="card fb-composer shadow-sm">
                    <div class="card-body">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0 fw-bold">Create Post</h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" id="visibilityDropdownButton"                                type="button"
                                    data-bs-toggle="dropdown">
                                    <i class="fas fa-globe"></i> <span id="visibilityLabel">Public</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" data-value="public" href="#"><i class="fas fa-globe me-2"></i>Public</a>
                                    </li>
                                    <li><a class="dropdown-item" data-value="friends" href="#"><i class="fas fa-users me-2"></i>Friends</a>
                                    </li>
                                    <li><a class="dropdown-item" data-value="only_me" href="#"><i class="fas fa-lock me-2"></i>Only me</a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- User Info -->
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'User') }}&background=random&size=40&rounded=true"
                                alt="{{ Auth::user()->name ?? 'User' }}" class="rounded-circle me-2" width="40"
                                height="40">
                            <div>
                                <div class="fw-semibold">{{ Auth::user()->name ?? 'Your Name' }}</div>
                                <small class="text-muted">Now</small>
                            </div>
                        </div>

                        <!-- Post Form -->
                        <form id="postForm" action="{{ url('admin/posts/facebook') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <input type="hidden" name="view_mode" id="view_mode" value="public">
                                <textarea name="content" id="postContent" class="form-control border-0 p-0" rows="3"
                                    placeholder="What's on your mind, {{ Auth::user()->name ?? 'User' }}?"
                                    style="resize: none; font-size: 1.1rem; outline: none; box-shadow: none;"></textarea>
                            </div>

                            <!-- Media Attachments Section -->
                            <div class="border-top pt-3 mt-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-semibold"><i class="fas fa-paperclip me-1"></i> Attachments</span>
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3"
                                        data-bs-toggle="modal" data-bs-target="#mediaLibraryModal">
                                        <i class="fas fa-plus me-1"></i> Add Media
                                    </button>
                                </div>

                                <!-- Selected Media Preview -->
                                <div id="selectedMediaContainer" class="d-flex flex-wrap gap-2">
                                    <!-- Dynamic thumbnails will appear here -->
                                </div>

                                <!-- Hidden inputs for existing media IDs -->
                                <div id="selectedMediaIds"></div>

                                <!-- Hidden inputs for new file uploads -->
                                <input type="file" name="new_media[]" id="newMediaInput" class="d-none" multiple
                                    accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                            </div>

                            <!-- Schedule Toggle & Picker -->
                            <div class="border-top pt-3 mt-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="scheduleToggle"
                                                style="width: 3rem; height: 1.5rem;">
                                            <label class="form-check-label fw-semibold" for="scheduleToggle">Schedule
                                                Post</label>
                                        </div>
                                        <div id="datetimePicker" class="d-none" style="min-width: 200px;">
                                            <input type="text" name="scheduled_at" id="scheduledAt" class="form-control"
                                                placeholder="Select date & time" readonly>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" id="postButton"
                                        disabled>
                                        Post
                                    </button>
                                </div>
                            </div>

                            <!-- Hidden status -->
                            <input type="hidden" name="status" id="postStatus" value="published">
                        </form>
                    </div>
                </div>
            </div>

            <!-- Scheduled Posts Sidebar -->
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-clock me-2"></i> Scheduled Posts
                    </div>
                    <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                        @if ($scheduledPosts->count())
                            <ul class="list-group list-group-flush">
                                @foreach ($scheduledPosts as $post)
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-semibold text-truncate" style="max-width: 180px;">
                                                {{ Str::limit($post->content, 30) }}
                                            </div>
                                            <small class="text-muted">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                {{ $post->scheduled_at->format('M d, Y H:i') }}
                                            </small>
                                        </div>
                                        <span
                                            class="badge bg-{{ $post->scheduled_at->isPast() ? 'warning' : 'primary' }} rounded-pill">
                                            {{ $post->scheduled_at->isPast() ? 'Past' : 'Upcoming' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-plus fa-2x mb-2 d-block"></i>
                                No scheduled posts yet.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Media Library Modal -->
    <div class="modal fade" id="mediaLibraryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Media Library</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="mediaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="library-tab" data-bs-toggle="tab"
                                data-bs-target="#library" type="button" role="tab">Library</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload"
                                type="button" role="tab">Upload New</button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3">
                        <!-- Library Tab -->
                        <div class="tab-pane fade show active" id="library" role="tabpanel">
                            <div class="row g-2" id="mediaGrid">
                                @forelse($userMedia as $media)
                                    <div class="col-4 col-md-3 media-item" data-media-id="{{ $media->id }}"
                                        data-media-url="{{ $media->media_url }}"
                                        data-media-type="{{ $media->media_type }}"
                                        data-thumbnail-url="{{ $media->thumbnail_url ?? '' }}"
                                        data-file-name="{{ $media->file_name }}">
                                        <div class="card h-100">
                                            @if (in_array($media->media_type, ['image', 'video']))
                                                <img src="{{ $media->thumbnail_url ?? $media->media_url }}"
                                                    class="card-img-top" alt="{{ $media->file_name }}"
                                                    style="height: 100px; object-fit: cover;">
                                            @else
                                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light"
                                                    style="height: 100px;">
                                                    <i class="fas fa-file fa-3x text-secondary"></i>
                                                </div>
                                            @endif
                                            <div class="card-body p-1 text-center">
                                                <small
                                                    class="text-truncate d-block">{{ Str::limit($media->file_name, 20) }}</small>
                                                <div class="form-check">
                                                    <input class="form-check-input media-checkbox" type="checkbox"
                                                        value="{{ $media->id }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-center text-muted py-4">
                                        <i class="fas fa-cloud-upload-alt fa-3x d-block mb-2"></i>
                                        No media found. Upload some first!
                                    </div>
                                @endforelse
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-primary" id="attachSelectedMedia">Attach
                                    Selected</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                        <!-- Upload Tab -->
                        <div class="tab-pane fade" id="upload" role="tabpanel">
                            <div class="dropzone p-4 border rounded text-center" id="dropzoneArea"
                                style="border-style: dashed; cursor: pointer;">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                <p class="mb-0">Drag & drop files here or click to browse</p>
                                <small class="text-muted">Supports images, videos, documents, spreadsheets</small>
                                <input type="file" id="dropzoneInput" class="d-none" multiple
                                    accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                            </div>
                            <div id="uploadPreviewContainer" class="row g-2 mt-3"></div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-success" id="uploadSelectedFiles">Upload &
                                    Attach</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

    <style>
        .fb-composer {
            border-radius: 12px;
            border: none;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .fb-composer .card-body {
            padding: 16px 20px;
        }

        textarea:focus {
            border-color: transparent !important;
        }

        .btn-light {
            background-color: #f0f2f5;
            border: none;
            transition: background 0.2s;
        }

        .btn-light:hover {
            background-color: #e4e6eb;
        }

        #postButton {
            background-color: #1877f2;
            border: none;
            min-width: 80px;
            transition: 0.2s;
        }

        #postButton:hover:not(:disabled) {
            background-color: #166fe5;
        }

        #postButton:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .dropdown-toggle::after {
            margin-left: 6px;
        }

        .form-switch .form-check-input {
            cursor: pointer;
        }

        .form-switch .form-check-input:checked {
            background-color: #1877f2;
            border-color: #1877f2;
        }

        #datetimePicker .flatpickr-input {
            border-radius: 20px;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
            background-color: #f0f2f5;
            border: none;
            cursor: pointer;
        }

        #datetimePicker .flatpickr-input:focus {
            box-shadow: none;
            border-color: #1877f2;
        }

        .list-group-item {
            border-left: none;
            border-right: none;
        }

        .list-group-item:first-child {
            border-top: none;
        }

        /* Selected Media Thumbnails */
        .media-thumb {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .media-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .media-thumb .file-icon {
            font-size: 2rem;
            color: #6c757d;
        }

        .media-thumb .remove-media {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #dc3545;
            color: #fff;
            border: none;
            font-size: 12px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .media-thumb .remove-media:hover {
            background: #c82333;
        }

        /* Modal grid */
        .media-item .card {
            cursor: pointer;
            transition: 0.15s;
        }

        .media-item .card:hover {
            box-shadow: 0 0 0 2px #1877f2;
        }

        .media-item .card-img-top {
            border-bottom: 1px solid #dee2e6;
        }

        .media-checkbox {
            transform: scale(1.2);
        }

        .dropzone {
            transition: 0.2s;
        }

        .dropzone.dragover {
            background-color: #e7f3ff;
            border-color: #1877f2;
        }
    </style>
@endpush

@push('scripts')
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ---- Visibility Dropdown ----
        const visibilityItems = document.querySelectorAll('.dropdown-menu .dropdown-item');
        const visibilityLabel = document.getElementById('visibilityLabel');
        const viewModeInput = document.getElementById('view_mode');

        visibilityItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const value = this.dataset.value;
                const label = this.textContent.trim();
                // Update hidden input
                viewModeInput.value = value;
                // Update button text (keep icon)
                const iconHtml = this.querySelector('i')?.outerHTML || '<i class="fas fa-globe"></i>';
                visibilityLabel.innerHTML = label;
                // Update the button icon if you want to change it per selection
                // For simplicity, we keep the globe icon, but you can change it.
                // Close the dropdown (optional)
                const dropdownButton = document.getElementById('visibilityDropdownButton');
                bootstrap.Dropdown.getInstance(dropdownButton)?.hide();
            });
        });
        // ---- DOM refs ----
        const textarea = document.getElementById('postContent');
        const postButton = document.getElementById('postButton');
        const scheduleToggle = document.getElementById('scheduleToggle');
        const datetimePicker = document.getElementById('datetimePicker');
        const scheduledAtInput = document.getElementById('scheduledAt');
        const postStatus = document.getElementById('postStatus');
        const selectedContainer = document.getElementById('selectedMediaContainer');
        const selectedIdsContainer = document.getElementById('selectedMediaIds');

        // ---- Flatpickr ----
        const fp = flatpickr(scheduledAtInput, {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true,
            defaultHour: 9,
            defaultMinute: 0,
            onChange: togglePostButton
        });

        // ---- Schedule Toggle ----
        scheduleToggle.addEventListener('change', function() {
            if (this.checked) {
                datetimePicker.classList.remove('d-none');
                postStatus.value = 'scheduled';
                if (!scheduledAtInput.value) {
                    const now = new Date();
                    now.setHours(now.getHours() + 1);
                    fp.setDate(now);
                }
            } else {
                datetimePicker.classList.add('d-none');
                postStatus.value = 'published';
                scheduledAtInput.value = '';
            }
            togglePostButton();
        });

        // ---- Textarea auto-resize ----
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
            togglePostButton();
        });

        // ---- Helper: get file icon class ----
        function getFileIconClass(type, fileName) {
            if (!type && !fileName) return 'fa-file';
            const ext = fileName ? fileName.split('.').pop().toLowerCase() : '';
            const mime = type || '';

            if (mime.startsWith('image/')) return 'fa-file-image';
            if (mime.startsWith('video/')) return 'fa-file-video';
            if (mime.startsWith('audio/')) return 'fa-file-audio';
            if (mime.includes('pdf')) return 'fa-file-pdf';
            if (mime.includes('word') || ext === 'doc' || ext === 'docx') return 'fa-file-word';
            if (mime.includes('excel') || ext === 'xls' || ext === 'xlsx' || ext === 'csv') return 'fa-file-excel';
            if (mime.includes('powerpoint') || ext === 'ppt' || ext === 'pptx') return 'fa-file-powerpoint';
            if (mime.includes('zip') || ext === 'zip' || ext === 'rar') return 'fa-file-archive';
            if (ext === 'txt') return 'fa-file-alt';
            if (ext === 'md') return 'fa-file-code';
            return 'fa-file';
        }

        // ---- Helper: generate video thumbnail (returns Promise) ----
        function generateVideoThumbnail(file) {
            return new Promise((resolve) => {
                const video = document.createElement('video');
                video.preload = 'metadata';
                video.muted = true;
                video.src = URL.createObjectURL(file);

                video.onloadeddata = function() {
                    video.currentTime = 0.1;
                };

                video.onseeked = function() {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth || 320;
                    canvas.height = video.videoHeight || 240;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const dataUrl = canvas.toDataURL('image/jpeg');
                    URL.revokeObjectURL(video.src);
                    resolve(dataUrl);
                };

                video.onerror = function() {
                    URL.revokeObjectURL(video.src);
                    resolve(null); // fallback: no thumbnail
                };

                video.load();
            });
        }

        // ---- Store selected media ----
        let selectedMedia = [];

        // ---- Render selected thumbnails ----
        function renderSelectedMedia() {
            selectedContainer.innerHTML = '';
            selectedIdsContainer.innerHTML = '';

            selectedMedia.forEach((item, index) => {
                const thumb = document.createElement('div');
                thumb.className = 'media-thumb';

                const isImage = item.type && item.type.startsWith('image/');
                const isVideo = item.type && item.type.startsWith('video/') || /\.(mp4|webm|ogg|mov|avi|mkv)$/i.test(item.name);

                if (isImage) {
                    const img = document.createElement('img');
                    img.src = item.url;
                    img.alt = item.name;
                    thumb.appendChild(img);
                } else if (isVideo) {
                    const wrapper = document.createElement('div');
                    wrapper.style.position = 'relative';
                    wrapper.style.width = '100%';
                    wrapper.style.height = '100%';
                    wrapper.style.display = 'flex';
                    wrapper.style.alignItems = 'center';
                    wrapper.style.justifyContent = 'center';

                    if (item.thumbnail) {
                        const img = document.createElement('img');
                        img.src = item.thumbnail;
                        img.alt = item.name;
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        wrapper.appendChild(img);
                    } else {
                        const icon = document.createElement('i');
                        icon.className = 'fas fa-file-video fa-3x text-secondary';
                        wrapper.appendChild(icon);
                    }

                    // Play overlay
                    const play = document.createElement('i');
                    play.className = 'fas fa-play-circle position-absolute';
                    play.style.fontSize = '2rem';
                    play.style.color = 'rgba(255,255,255,0.8)';
                    play.style.textShadow = '0 2px 4px rgba(0,0,0,0.5)';
                    play.style.top = '50%';
                    play.style.left = '50%';
                    play.style.transform = 'translate(-50%, -50%)';
                    wrapper.appendChild(play);
                    thumb.appendChild(wrapper);
                } else {
                    // Document
                    const icon = document.createElement('i');
                    icon.className = `fas ${getFileIconClass(item.type, item.name)} fa-3x text-secondary`;
                    thumb.appendChild(icon);
                    // Optionally show extension label
                    const ext = item.name.split('.').pop().toUpperCase();
                    const label = document.createElement('span');
                    label.className = 'position-absolute bottom-0 start-50 translate-middle-x small text-dark bg-white px-1 rounded';
                    label.style.fontSize = '0.6rem';
                    label.textContent = ext;
                    thumb.appendChild(label);
                }

                // Remove button
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-media';
                removeBtn.innerHTML = '×';
                removeBtn.type = 'button';
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    selectedMedia.splice(index, 1);
                    renderSelectedMedia();
                    togglePostButton();
                });
                thumb.appendChild(removeBtn);

                // Hidden input for existing media IDs
                if (item.id) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'media_ids[]';
                    hidden.value = item.id;
                    selectedIdsContainer.appendChild(hidden);
                }

                selectedContainer.appendChild(thumb);
            });
            togglePostButton();
        }

        // ---- Media Library Modal ----
        const modal = document.getElementById('mediaLibraryModal');
        const attachBtn = document.getElementById('attachSelectedMedia');
        const checkboxes = () => document.querySelectorAll('.media-checkbox:checked');

        attachBtn.addEventListener('click', function() {
            // const checked = checkboxes();
            // if (checked.length === 0) {
            //     alert('Please select at least one media item.');
            //     return;
            // }
            checked.forEach(cb => {
                const item = cb.closest('.media-item');
                const id = item.dataset.mediaId;
                const url = item.dataset.mediaUrl;
                const type = item.dataset.mediaType || 'image';
                const thumbnail = item.dataset.thumbnailUrl || '';
                const fileName = item.dataset.fileName || 'Media';
                if (!selectedMedia.some(m => m.id == id)) {
                    selectedMedia.push({
                        id: parseInt(id),
                        url: url,
                        type: type,
                        thumbnail: thumbnail,
                        name: fileName
                    });
                }
                cb.checked = false;
            });
            renderSelectedMedia();
            bootstrap.Modal.getInstance(modal).hide();
        });

        // ---- Upload New Tab ----
        const dropzone = document.getElementById('dropzoneArea');
        const dropzoneInput = document.getElementById('dropzoneInput');
        const uploadPreview = document.getElementById('uploadPreviewContainer');
        const uploadBtn = document.getElementById('uploadSelectedFiles');

        dropzone.addEventListener('click', () => dropzoneInput.click());

        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                handleFiles(e.dataTransfer.files);
            }
        });

        dropzoneInput.addEventListener('change', function() {
            if (this.files.length) {
                handleFiles(this.files);
                this.value = '';
            }
        });

        let pendingFiles = [];

        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (!pendingFiles.some(f => f.name === file.name && f.size === file.size)) {
                    pendingFiles.push(file);
                }
            });
            renderPendingUploads();
        }

        function renderPendingUploads() {
            uploadPreview.innerHTML = '';
            pendingFiles.forEach((file, index) => {
                const col = document.createElement('div');
                col.className = 'col-3';
                const card = document.createElement('div');
                card.className = 'card h-100';
                const preview = document.createElement('div');
                preview.className = 'card-img-top d-flex align-items-center justify-content-center bg-light';
                preview.style.height = '80px';
                preview.style.position = 'relative';

                const isImage = file.type.startsWith('image/');
                const isVideo = file.type.startsWith('video/') || /\.(mp4|webm|ogg|mov|avi|mkv)$/i.test(file.name);

                if (isImage) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    preview.appendChild(img);
                } else if (isVideo) {
                    // Show a loading indicator or icon; thumbnail will be generated on attach
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-file-video fa-3x text-secondary';
                    preview.appendChild(icon);
                    const play = document.createElement('i');
                    play.className = 'fas fa-play-circle position-absolute';
                    play.style.fontSize = '2rem';
                    play.style.color = 'rgba(255,255,255,0.7)';
                    play.style.top = '50%';
                    play.style.left = '50%';
                    play.style.transform = 'translate(-50%, -50%)';
                    preview.appendChild(play);
                } else {
                    const icon = document.createElement('i');
                    icon.className = `fas ${getFileIconClass(file.type, file.name)} fa-3x text-secondary`;
                    preview.appendChild(icon);
                }

                const body = document.createElement('div');
                body.className = 'card-body p-1 text-center';
                const name = document.createElement('small');
                name.className = 'text-truncate d-block';
                name.textContent = file.name;
                const size = document.createElement('small');
                size.className = 'text-muted';
                size.textContent = (file.size / 1024).toFixed(1) + ' KB';
                body.appendChild(name);
                body.appendChild(size);
                card.appendChild(preview);
                card.appendChild(body);

                const removeBtn = document.createElement('button');
                removeBtn.className = 'btn btn-sm btn-danger position-absolute top-0 end-0 m-1';
                removeBtn.style.borderRadius = '50%';
                removeBtn.style.width = '24px';
                removeBtn.style.height = '24px';
                removeBtn.style.padding = '0';
                removeBtn.innerHTML = '×';
                removeBtn.type = 'button';
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    pendingFiles.splice(index, 1);
                    renderPendingUploads();
                });
                card.style.position = 'relative';
                card.appendChild(removeBtn);
                col.appendChild(card);
                uploadPreview.appendChild(col);
            });
        }

        // ---- Upload & Attach (with video thumbnail generation) ----
        uploadBtn.addEventListener('click', function() {
            if (pendingFiles.length === 0) {
                alert('Please select files to upload.');
                return;
            }

            // Generate thumbnails for video files
            const promises = pendingFiles.map(file => {
                const isVideo = file.type.startsWith('video/') || /\.(mp4|webm|ogg|mov|avi|mkv)$/i.test(file.name);
                if (isVideo) {
                    return generateVideoThumbnail(file).then(thumbnail => ({ file, thumbnail }));
                } else {
                    return Promise.resolve({ file, thumbnail: null });
                }
            });

            Promise.all(promises).then(results => {
                results.forEach(({ file, thumbnail }) => {
                    const url = URL.createObjectURL(file);
                    selectedMedia.push({
                        id: null,
                        file: file,
                        url: url,
                        type: file.type,
                        thumbnail: thumbnail,
                        name: file.name
                    });
                });
                pendingFiles = [];
                renderPendingUploads();
                renderSelectedMedia();
                bootstrap.Modal.getInstance(modal).hide();
            });
        });

        // ---- Form Submission ----
        const form = document.getElementById('postForm');
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const newFiles = selectedMedia.filter(item => item.id === null).map(item => item.file);
            if (newFiles.length) {
                newFiles.forEach(file => {
                    formData.append('new_media[]', file);
                });
            }

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect || '/admin/posts/facebook';
                } else {
                    alert('Error: ' + (data.message || 'Something went wrong.'));
                }
            })
            .catch(err => {
                alert('Network error. Please try again.');
            });
        });

        // ---- Utility ----
        function togglePostButton() {
            const content = textarea.value.trim();
            const hasMedia = selectedMedia.length > 0;
            let isValid = content.length > 0 || hasMedia;
            if (scheduleToggle.checked) {
                isValid = isValid && scheduledAtInput.value !== '';
            }
            postButton.disabled = !isValid;
        }

        togglePostButton();

        window.addEventListener('beforeunload', function() {
            selectedMedia.forEach(item => {
                if (item.id === null && item.url) {
                    URL.revokeObjectURL(item.url);
                }
            });
        });
    });
</script>
@endpush
