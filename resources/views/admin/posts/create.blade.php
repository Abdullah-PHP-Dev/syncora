@extends('layouts.app')

@section('title', 'Create Facebook Post')

@push('styles')
    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    {{-- Google Font (optional, but adds polish) --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           ROOT VARIABLES – Professional Design System
           ============================================================ */
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #818cf8;
            --primary-bg: #eef2ff;
            --secondary: #64748b;
            --surface: #ffffff;
            --background: #f8fafc;
            --border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 25px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
            --radius: 0.75rem;
            --radius-sm: 0.5rem;
            --radius-lg: 1rem;
            --transition: all 0.2s ease;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--background);
            color: var(--text-primary);
        }

        /* ============================================================
           CARDS & CONTAINERS
           ============================================================ */
        .card {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            background: var(--surface);
        }
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: -0.01em;
            color: var(--text-primary);
        }
        .card-body {
            padding: 1.5rem;
        }

        /* ============================================================
           FORM ELEMENTS
           ============================================================ */
        .form-control, .form-select {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--surface);
            color: var(--text-primary);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        .form-control::placeholder {
            color: var(--text-muted);
        }
        textarea.form-control {
            resize: vertical;
        }

        label {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.4rem;
        }

        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn-primary {
            background: var(--primary);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .btn-outline-secondary {
            border: 1.5px solid var(--border);
            color: var(--text-secondary);
            font-weight: 500;
            transition: var(--transition);
        }
        .btn-outline-secondary:hover {
            background: var(--background);
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-sm {
            padding: 0.3rem 0.8rem;
            font-size: 0.8rem;
        }

        /* ============================================================
           AI ASSISTANT – Professional Redesign
           ============================================================ */
        .ai-container {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .ai-container-header {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: white;
            padding: 1rem 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }
        .ai-container-header:hover {
            background: linear-gradient(135deg, #1e293b, #1e293b);
        }

        .ai-header-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.08);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .ai-header-info .subtitle {
            font-size: 0.75rem;
            opacity: 0.7;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .ai-header-info .subtitle .dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        .ai-body {
            padding: 1.5rem;
            display: none;
            background: var(--background);
        }
        .ai-body.active {
            display: block;
        }

        /* Quick chips */
        .quick-chip {
            padding: 0.35rem 1.2rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
            color: var(--text-secondary);
        }
        .quick-chip:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* Messages */
        .ai-messages-container {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1.5rem;
            max-height: 520px;
            overflow-y: auto;
            border: 1px solid var(--border);
            min-height: 220px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }

        .ai-msg {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .ai-msg.user {
            flex-direction: row-reverse;
        }
        .ai-msg .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            flex-shrink: 0;
            background: var(--border);
            color: var(--text-secondary);
        }
        .ai-msg .avatar.bot {
            background: linear-gradient(135deg, #334155, #0f172a);
            color: white;
        }
        .ai-msg .avatar.user {
            background: var(--primary-bg);
            color: var(--primary);
        }
        .ai-msg .bubble {
            max-width: 78%;
            padding: 0.7rem 1.2rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            line-height: 1.6;
            word-wrap: break-word;
            box-shadow: var(--shadow-sm);
        }
        .ai-msg .bubble.bot {
            background: var(--surface);
            border: 1px solid var(--border);
            border-bottom-left-radius: 0.25rem;
        }
        .ai-msg .bubble.user {
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }
        .ai-msg.system .bubble {
            background: var(--border);
            color: var(--text-secondary);
            font-size: 0.75rem;
            padding: 0.25rem 1.2rem;
            border-radius: 2rem;
            max-width: 90%;
            text-align: center;
            margin: 0 auto;
        }
        .ai-msg.system {
            justify-content: center;
        }

        /* Suggestion cards */
        .suggestion-card {
            background: var(--background);
            border-radius: var(--radius-sm);
            padding: 1rem 1.25rem;
            margin-top: 0.75rem;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }
        .suggestion-card:hover {
            background: var(--surface);
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }
        .suggestion-card .label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        .suggestion-card .label .badge-type {
            font-size: 0.55rem;
            padding: 0.1rem 0.7rem;
            border-radius: 1rem;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
        }
        .badge-type.title { background: #dbeafe; color: #1e40af; }
        .badge-type.contents { background: #d1fae5; color: #065f46; }
        .badge-type.hashtags { background: #fce4ec; color: #9b2c2c; }
        .badge-type.media { background: #fef3c7; color: #92400e; }
        .badge-type.image-gen { background: #ede9fe; color: #5b21b6; }
        .suggestion-card .value {
            font-size: 0.875rem;
            color: var(--text-primary);
            margin: 0.25rem 0 0.75rem 0;
            line-height: 1.7;
        }
        .suggestion-card .value.title-text {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .suggestion-card .value.hashtags-text {
            font-weight: 500;
            color: var(--primary);
        }

        .action-btn {
            padding: 0.25rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .action-btn.primary { background: var(--primary); color: white; }
        .action-btn.primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .action-btn.success { background: #10b981; color: white; }
        .action-btn.success:hover { background: #059669; transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .action-btn.outline { background: transparent; color: var(--text-secondary); border: 1.5px solid var(--border); }
        .action-btn.outline:hover { background: var(--background); border-color: var(--primary); color: var(--primary); }
        .action-btn.sm { padding: 0.15rem 0.75rem; font-size: 0.6rem; }
        .action-group { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; }

        /* Apply all */
        .apply-all-btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .apply-all-btn:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: var(--shadow-md); }

        /* Toggle switch */
        .twsa-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }
        .twsa-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .twsa-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #cbd5e1;
            border-radius: 34px;
            transition: var(--transition);
        }
        .twsa-slider:before {
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: var(--transition);
            position: absolute;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .twsa-switch input:checked + .twsa-slider {
            background: var(--primary);
        }
        .twsa-switch input:checked + .twsa-slider:before {
            transform: translateX(20px);
        }

        /* Platform restriction badge */
        .platform-restriction-badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            font-size: 0.6rem;
            padding: 0.1rem 0.6rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }
        .platform-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        .platform-disabled .twsa-switch {
            cursor: not-allowed;
        }
        .platform-disabled .platform-name {
            color: var(--text-muted) !important;
        }

        /* Platform card */
        .platform-card-wrapper {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            background: var(--surface);
            transition: var(--transition);
        }
        .platform-card-wrapper:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-sm);
        }
        .platform-card-wrapper .platform-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .platform-page-card {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
        }
        .platform-page-card .page-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }
        .platform-page-card .page-item:last-child {
            border-bottom: none;
        }

        /* ============================================================
           PREVIEW CARD – Social Media Mock
           ============================================================ */
        .preview-card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }
        .preview-card .preview-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .preview-card .preview-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .preview-card .preview-user {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .preview-card .preview-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .preview-card .preview-content {
            padding: 1rem;
            min-height: 60px;
            /* white-space: pre-wrap; */
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--text-primary);
        }
        .preview-card .preview-media {
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 240px;
            position: relative;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .preview-card .preview-media img,
        .preview-card .preview-media video {
            max-width: 100%;
            max-height: 280px;
            object-fit: contain;
        }
        .preview-card .preview-actions {
            display: flex;
            justify-content: space-around;
            padding: 0.6rem 1rem;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--border);
        }
        .preview-card .preview-actions i {
            margin-right: 0.3rem;
        }

        /* ============================================================
           FILE UPLOAD AREA
           ============================================================ */
        .upload-dropzone {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 2.5rem 1.5rem;
            text-align: center;
            background: var(--background);
            cursor: pointer;
            transition: var(--transition);
        }
        .upload-dropzone:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
        }
        .upload-dropzone i {
            font-size: 2.5rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        .upload-dropzone h5 {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .upload-dropzone p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0;
        }

        /* Media preview thumbnail inside upload area */
        .media-preview-thumb {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--background);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
        }
        .media-preview-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-preview-thumb video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-preview-thumb .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            pointer-events: none;
        }

        /* ============================================================
           STICKY PUBLISH BAR
           ============================================================ */
        .publish-bar {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 1rem 0;
            position: sticky;
            bottom: 0;
            z-index: 100;
            backdrop-filter: blur(8px);
            background: rgba(255,255,255,0.92);
        }

        /* ============================================================
           MISC
           ============================================================ */
        .text-muted {
            color: var(--text-muted) !important;
        }
        .text-secondary {
            color: var(--text-secondary) !important;
        }
        .bg-light {
            background: var(--background) !important;
        }
        .border {
            border-color: var(--border) !important;
        }

        /* Thinking dots */
        .thinking-dots {
            display: inline-flex;
            gap: 4px;
            align-items: center;
        }
        .thinking-dots span {
            width: 8px;
            height: 8px;
            background: var(--text-muted);
            border-radius: 50%;
            animation: dotBounce 1.4s infinite;
        }
        .thinking-dots span:nth-child(2) { animation-delay: 0.2s; }
        .thinking-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes dotBounce {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.3; }
            30% { transform: translateY(-6px); opacity: 1; }
        }

        .generated-image-preview img {
            max-width: 100%;
            max-height: 250px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            object-fit: cover;
        }

        .media-thumb {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .media-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-thumb .remove-media {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            border: none;
            font-size: 12px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* ============================================================
           RESPONSIVE TWEAKS
           ============================================================ */
        @media (max-width: 767.98px) {
            .ai-container-header {
                flex-wrap: wrap;
            }
            .ai-header-info .subtitle {
                font-size: 0.65rem;
            }
            .quick-chip {
                font-size: 0.65rem;
                padding: 0.2rem 0.8rem;
            }
            .ai-messages-container {
                max-height: 380px;
            }
            .publish-bar .d-flex {
                flex-direction: column;
                gap: 0.5rem;
            }
            .publish-bar .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
<div class="container py-4">
    {{-- AI ASSISTANT --}}
    <div class="ai-container mb-4">
        <div class="ai-container-header" onclick="toggleAIContainer()">
            <div class="d-flex align-items-center gap-3">
                <div class="ai-header-icon">
                    <i class="fas fa-wand-magic-sparkles"></i>
                </div>
                <div class="ai-header-info">
                    <h5 class="mb-0">SocialeazAI Content Assistant</h5>
                    <div class="subtitle">
                        <span class="dot"></span>
                        <span>Powered by SocialeazAI</span>
                        <span class="opacity-50 ms-1">v2.0</span>
                    </div>
                </div>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-light border-0" id="aiToggleBtn"
                        onclick="event.stopPropagation(); toggleAIContainer();">
                    <i class="fas fa-chevron-up" id="aiToggleIcon"></i>
                    <span id="aiToggleText">Collapse</span>
                </button>
            </div>
        </div>

        <div class="ai-body active" id="aiBody">
            {{-- Quick chips --}}
            <div class="mb-3">
                <div class="d-flex align-items-center gap-2 small text-secondary mb-2">
                    <i class="fas fa-bolt"></i> Quick Prompts
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="quick-chip" data-prompt="Write content for a new fashion collection launch">👗 Fashion</span>
                    <span class="quick-chip" data-prompt="Write content for a tech gadget promotion">💻 Tech</span>
                    <span class="quick-chip" data-prompt="Write content for a food delivery service">🍕 Food</span>
                    <span class="quick-chip" data-prompt="Write content for a fitness program">💪 Fitness</span>
                    <span class="quick-chip" data-prompt="Write content for a beauty product launch">💄 Beauty</span>
                    <span class="quick-chip" data-prompt="Write engaging social media content for a travel agency">✈️ Travel</span>
                    <span class="quick-chip" data-prompt="Write promotional content for a SaaS product">☁️ SaaS</span>
                </div>
            </div>

            {{-- Messages --}}
            <div class="ai-messages-container" id="aiMessages">
                <div class="ai-msg" id="welcomeMsg">
                    <div class="avatar bot">AI</div>
                    <div class="bubble bot">
                        <div class="fw-bold fs-6 mb-1">✨ Welcome to AI Content Assistant!</div>
                        <div class="text-secondary small mb-2">Describe your product or campaign and I'll generate:</div>
                        <ul class="mb-2 ps-3 small text-secondary">
                            <li>📌 <strong>Title</strong> – Catchy headline</li>
                            <li>📝 <strong>Description</strong> – Engaging content</li>
                            <li>🏷️ <strong>Hashtags</strong> – Trending keywords</li>
                            <li>🖼️ <strong>Media suggestions</strong> – Visual ideas</li>
                        </ul>
                        <div class="small text-secondary-emphasis">Try one of the quick prompts above or type your own!</div>
                    </div>
                </div>
            </div>

            {{-- Media attachment --}}
            <div class="d-flex align-items-center gap-3 flex-wrap mt-3">
                <label for="aiMediaInput" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-paperclip"></i> Attach Media
                </label>
                <input type="file" id="aiMediaInput" class="d-none" accept="image/*,video/*,image/gif">
                <div id="aiMediaPreview" class="d-none"></div>
                <span id="mediaFileName" class="small text-secondary"></span>
            </div>

            {{-- Input row --}}
            <div class="d-flex gap-3 mt-3 flex-wrap">
                <div class="flex-grow-1 d-flex gap-2" style="min-width:250px;">
                    <input type="text" id="aiInput" class="form-control form-control-sm" placeholder="Describe what you want to post...">
                    <button class="btn btn-primary btn-sm" id="aiSendBtn">
                        <i class="fas fa-paper-plane"></i> Generate
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold">{{ __('admin.marketing_tools.posts.post-create-header') }}</h2>
            <p class="text-muted mb-0">{{ __('admin.marketing_tools.posts.create_post_description') }}</p>
        </div>
    </div>

    {{-- Errors --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Main Form --}}
    <form id="post" action="{{ route('admin.posts.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row g-4">
            {{-- Left column --}}
            <div class="col-lg-8">

                {{-- Content Composer --}}
                <div class="card mb-4">
                    <div class="card-header bg-light fw-semibold">{{ __('admin.marketing_tools.posts.content_composer') }}</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="postContent" class="fw-semibold">{{ __('admin.marketing_tools.posts.post_content') }}</label>
                            <textarea name="content" id="postContent" class="form-control" rows="6"
                                      placeholder="{{ __('admin.marketing_tools.posts.content_placeholder') }}">{{ old('content') }}</textarea>
                            <p class="text-danger error-content small"></p>
                            <div class="text-end text-muted small">
                                <span id="charCount">0</span> {{ __('admin.marketing_tools.posts.characters') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Media Upload --}}
                <div class="card mb-4">
                    <div class="card-header bg-light fw-semibold">{{ __('admin.marketing_tools.posts.media_upload') }}</div>
                    <div class="card-body">
                        <div class="upload-dropzone" onclick="document.getElementById('mediaInput').click();">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h5>{{ __('admin.marketing_tools.posts.drop_file') }}</h5>
                            <p>{{ __('admin.marketing_tools.posts.file_extension') }}</p>
                            <input type="file" name="media[]" id="mediaInput" multiple class="d-none" accept="image/*,video/*">
                        </div>
                        <div id="mediaPreviewWrapper" class="d-none mt-3">
                            <div class="d-flex align-items-center gap-3 p-2 bg-light rounded-3 border">
                                <!-- Thumbnail preview -->
                                <div id="mediaPreviewThumb" class="media-preview-thumb d-none">
                                    <img id="thumbImage" src="" alt="Thumbnail" class="d-none">
                                    <video id="thumbVideo" src="" class="d-none" muted></video>
                                    <div id="thumbOverlay" class="play-overlay d-none">
                                        <i class="fas fa-play-circle"></i>
                                    </div>
                                </div>
                                <div class="d-flex flex-column">
                                    <span id="mediaPreviewFileName" class="small fw-semibold">File uploaded</span>
                                    <span id="mediaPreviewFileSize" class="text-muted small"></span>
                                </div>
                                <button type="button" onclick="clearAllMedia()" class="btn btn-sm btn-outline-danger ms-auto">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-danger error-media small"></p>
                    </div>
                </div>

                {{-- Hidden AI fields --}}
                <input type="hidden" name="ai_media" id="ai_media">
                <input type="hidden" name="ai_image_url" id="ai_image_url">

                {{-- Category --}}
                <div class="card mb-4">
                    <div class="card-header bg-light fw-semibold">{{ __('admin.marketing_tools.posts.category') }}</div>
                    <div class="card-body">
                        <div class="d-flex gap-2">
                            <select name="category_id" id="category_id" class="form-select">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                        {{ (isset($post) && $post->category_id == $category->id) || $category->id == request()->category ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#addCategoryModal">
                                + {{ __('admin.marketing_tools.posts.add') }}
                            </button>
                        </div>
                        <p class="text-danger error-category_id small"></p>
                    </div>
                </div>

                {{-- Platforms --}}
                <div class="card mb-4">
                    <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
                        <span>{{ __('admin.marketing_tools.posts.select_platforms') }}</span>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('admin.post-accounts.meta.redirect') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fab fa-facebook"></i> Connect Facebook
                            </a>
                            <a href="{{ route('admin.post-accounts.instagram.redirect') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fab fa-instagram"></i> Connect Instagram
                            </a>
                            <a href="{{ route('admin.post-accounts.threads.redirect') }}" class="btn btn-outline-dark btn-sm">
                                <i class="fab fa-threads"></i> Connect Threads
                            </a>
                            <a href="{{ route('admin.post-accounts.pinterest.redirect') }}" class="btn btn-outline-danger btn-sm">
                                <i class="fab fa-pinterest"></i> Connect Pinterest
                            </a>
                            <a href="{{ route('admin.post-accounts.x.redirect') }}" class="btn btn-outline-dark btn-sm">
                                <i class="fab fa-x-twitter"></i> Connect X
                            </a>
                            <a href="{{ route('admin.post-accounts.linkedin.redirect') }}" class="btn btn-outline-info btn-sm">
                                <i class="fab fa-linkedin"></i> Connect LinkedIn
                            </a>
                            <a href="{{ route('admin.post-accounts.tiktok.redirect') }}" class="btn btn-outline-dark btn-sm">
                                <i class="fab fa-tiktok"></i> Connect TikTok
                            </a>
                            <a href="{{ route('admin.post-accounts.google.redirect') }}" class="btn btn-outline-danger btn-sm">
                                <i class="fab fa-google"></i> Connect Google / YouTube
                            </a>
                            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#connectWhatsappModal">
                                <i class="fab fa-whatsapp"></i> Connect WhatsApp
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        @php
                            $platforms = ['facebook', 'instagram', 'x', 'tiktok', 'youtube', 'linkedin', 'google', 'whatsapp', 'threads', 'pinterest'];
                            $selected = $post->platforms ?? [];
                        @endphp

                        @foreach ($platforms as $platform)
                            @php
                                $socialPages = $accounts->where('platform', $platform)->whereNotNull('access_token');
                                $isVideoPlatform = in_array($platform, ['tiktok', 'youtube']);
                            @endphp
                            @if ($socialPages->count() > 0)
                                <div class="platform-card-wrapper"
                                     data-platform="{{ $platform }}"
                                     data-video-platform="{{ $isVideoPlatform ? 'true' : 'false' }}">
                                    <div class="d-flex justify-content-between align-items-center" onclick="togglePlatformPages('{{ $platform }}')">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fab fa-{{ $platform == 'x' ? 'x-twitter' : $platform }} fa-lg text-secondary" style="width: 24px; text-align: center;"></i>
                                            <span class="platform-name text-capitalize">{{ $platform }}</span>
                                            <span class="badge bg-light text-secondary">{{ $socialPages->count() }} pages</span>
                                            @if ($isVideoPlatform)
                                                <span class="platform-restriction-badge d-none" id="restrictionBadge_{{ $platform }}">
                                                    <i class="fas fa-info-circle"></i> Requires Video
                                                </span>
                                            @endif
                                        </div>
                                        <div onclick="event.stopPropagation();">
                                            <label class="twsa-switch">
                                                <input type="checkbox" name="platforms[]" value="{{ $platform }}"
                                                       {{ $socialPlatform == $platform ? 'checked' : '' }}
                                                       onchange="togglePlatformSelection('{{ $platform }}', this.checked)"
                                                       id="platformCheckbox_{{ $platform }}">
                                                <span class="twsa-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="platform-page-card {{ $socialPlatform == $platform ? 'd-block' : 'd-none' }}"
                                         id="platformPages_{{ $platform }}">
                                        @foreach ($socialPages as $page)
                                            <div class="page-item">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="{{ $page->image ?? 'https://twsaa.com/images/new_frontend/new-logo-icon.png' }}"
                                                         class="rounded-circle" width="32" height="32"
                                                         onerror="this.src='https://twsaa.com/images/new_frontend/new-logo-icon.png'"
                                                         alt="{{ $page->name }}">
                                                    <div>
                                                        <div class="fw-semibold small">{{ $page->name }}</div>
                                                        <div class="text-muted small">{{ $page->platform }}</div>
                                                    </div>
                                                </div>
                                                <label class="twsa-switch">
                                                    <input type="checkbox" name="{{ $platform }}[pages][]" value="{{ $page->id }}"
                                                           {{ $page->id == request()->query('pageId') ? 'checked' : '' }}
                                                           id="pageCheckbox_{{ $page->id }}">
                                                    <span class="twsa-slider"></span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        <p class="text-danger error-platforms small"></p>

                        {{-- WhatsApp has no public feed - a "post" here is a
                             broadcast: an already-approved Message Template
                             sent to a list of numbers. See WhatsAppPostService
                             for why templates are required. --}}
                        <div id="whatsappBroadcastFields" class="{{ $socialPlatform == 'whatsapp' ? '' : 'd-none' }} mt-4 p-3 border rounded">
                            <h6 class="fw-semibold"><i class="fab fa-whatsapp text-success"></i> WhatsApp Broadcast Settings</h6>
                            <p class="text-muted small mb-3">WhatsApp requires an approved Message Template for outbound broadcasts - free-form text only works within an active customer chat. Your post's caption fills the template's body text, and any attached image fills its header.</p>
                            <div class="mb-3">
                                <label class="form-label">Recipient Phone Numbers *</label>
                                <textarea name="whatsapp_recipients" class="form-control" rows="3" placeholder="15551234567, 15559876543&#10;or one per line"></textarea>
                                <p class="text-danger error-whatsapp_recipients small"></p>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Approved Template Name *</label>
                                    <input type="text" name="whatsapp_template_name" class="form-control" placeholder="e.g. order_update">
                                    <p class="text-danger error-whatsapp_template_name small"></p>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Template Language</label>
                                    <input type="text" name="whatsapp_template_language" class="form-control" value="en_US">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Publishing Settings --}}
                <div class="card mb-4">
                    <div class="card-header bg-light fw-semibold">{{ __('admin.marketing_tools.posts.publishing_setting') }}</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>{{ __('admin.marketing_tools.posts.schedule_post') }}</strong>
                                <label class="twsa-switch">
                                    <input type="checkbox" id="schedule_mode" name="schedule_mode" value="1">
                                    <span class="twsa-slider"></span>
                                </label>
                            </div>
                            <input type="datetime-local" name="schedule_at" class="form-control mt-2"
                                   value="{{ now()->addMinutes(15)->format('Y-m-d\TH:i') }}">
                            <p class="text-danger error-schedule_at small"></p>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>{{ __('admin.marketing_tools.posts.auto_expiry') }}</strong>
                                <label class="twsa-switch">
                                    <input type="checkbox" id="expiry_mode" name="expiry_mode" value="1">
                                    <span class="twsa-slider"></span>
                                </label>
                            </div>
                            <input type="datetime-local" name="expiry_at" class="form-control mt-2"
                                   value="{{ old('expiry_at', now()->addDay()->format('Y-m-d\TH:i')) }}">
                            <p class="text-danger error-expiry_at small"></p>
                        </div>
                    </div>
                </div>

                {{-- Publish Bar --}}
                <div class="publish-bar">
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4 py-2">{{ __('admin.marketing_tools.posts.publish_post') }}</button>
                    </div>
                </div>
            </div>

            {{-- Right column: Preview --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-light fw-semibold">{{ __('admin.marketing_tools.posts.live_preview') }}</div>
                    <div class="card-body">
                        <div class="preview-card">
                            <div class="preview-header">
                                <div class="preview-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div class="preview-user">@ {{ Auth::user()->name ?? 'User' }}</div>
                                    <div class="preview-time">{{ __('admin.marketing_tools.posts.preview') }}</div>
                                </div>
                            </div>
                            <div class="preview-content" id="previewText">
                                {{ __('admin.marketing_tools.posts.start_typing') }}
                            </div>
                            <div class="preview-media" id="previewMediaContainer">
                                <div id="previewPlaceholder" class="text-center text-muted">
                                    <i class="fas fa-image fa-3x mb-2" style="opacity:0.3;"></i>
                                    <p class="small">{{ __('admin.marketing_tools.posts.media_preview') }}</p>
                                </div>
                                <img id="previewImage" class="d-none" style="max-width:100%; max-height:280px; object-fit:contain;">
                                <video id="previewVideo" class="d-none" style="max-width:100%; max-height:280px;" controls></video>
                            </div>
                            <div class="preview-actions">
                                <span><i class="far fa-heart"></i> Like</span>
                                <span><i class="far fa-comment"></i> Comment</span>
                                <span><i class="far fa-share-square"></i> Share</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Add Category Modal --}}
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('admin.marketing_tools.posts.add_category') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="name" class="form-control mb-3" placeholder="Category name">
                <textarea id="description" class="form-control mb-3" rows="3" placeholder="{{ __('admin.marketing_tools.posts.category_placeholder') }}"></textarea>
                <button class="btn btn-primary w-100" id="saveCategoryBtn">{{ __('admin.marketing_tools.posts.save_category') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- Connect WhatsApp Modal --}}
<div class="modal fade" id="connectWhatsappModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fab fa-whatsapp text-success"></i> Connect WhatsApp Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if (adminSetting('messaging.meta.whatsapp_config_id'))
                    <button type="button" id="whatsappEmbeddedSignupBtn" class="btn btn-success w-100 mb-2">
                        <i class="fab fa-facebook"></i> Connect with Facebook
                    </button>
                    <p class="text-muted small text-center mb-3">Verifies your number and creates the WhatsApp Business Account automatically.</p>
                    <div class="text-center mb-3"><span class="text-muted small">— or enter credentials manually —</span></div>
                @else
                    <p class="text-muted small">"Connect with Facebook" isn't configured yet (needs a WhatsApp Embedded Signup config_id from your Meta App Dashboard). Enter credentials manually for now:</p>
                @endif

                <form action="{{ route('admin.post-accounts.whatsapp.store') }}" method="POST">
                    @csrf
                    <p class="text-muted small">Paste the Phone Number ID and a permanent access token from your Meta Business System User - the same credentials used for the WhatsApp Business Cloud API.</p>
                    <div class="mb-3">
                        <label class="form-label">Display Name *</label>
                        <input type="text" name="name" class="form-control" required>
                        @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number ID *</label>
                        <input type="text" name="phone_number_id" class="form-control" required>
                        @error('phone_number_id')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Permanent Access Token *</label>
                        <input type="text" name="access_token" class="form-control" required>
                        @error('access_token')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-outline-success w-100">Connect Manually</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- JavaScript – unchanged logic with enhanced media preview --}}
    <script>
        // The WhatsApp account connect form is a plain server-rendered
        // POST/redirect (unlike the rest of this page's AJAX flow), so its
        // outcome arrives as a session flash rather than an AJAX response -
        // bridge it into the same SweetAlert2 feedback the rest of the page
        // uses.
        @if (session('success'))
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({ icon: 'success', title: 'Success', text: @json(session('success')) });
            });
        @endif
        @if (session('error'))
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({ icon: 'error', title: 'Error', text: @json(session('error')) });
            });
        @endif

        // ============================================
        // WHATSAPP EMBEDDED SIGNUP
        // ("Connect with Facebook" - see PostAccountController::
        // storeWhatsappEmbedded for the backend half of this flow)
        // ============================================
        @if (adminSetting('messaging.meta.whatsapp_config_id'))
            window.fbAsyncInit = function() {
                FB.init({
                    appId: '{{ adminSetting('messaging.meta.app_id') }}',
                    cookie: true,
                    xfbml: true,
                    version: '{{ adminSetting('messaging.meta.graph_version') ?: 'v21.0' }}'
                });
            };

            (function(d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s); js.id = id;
                js.src = "https://connect.facebook.net/en_US/sdk.js";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));

            // Embedded Signup returns the newly created WABA ID and phone
            // number ID via postMessage to this window - not through the
            // FB.login() callback itself, which only carries the
            // exchangeable authorization code.
            let waEmbeddedSessionInfo = {};

            window.addEventListener('message', function(event) {
                if (!event.origin.endsWith('facebook.com')) return;

                try {
                    var data = JSON.parse(event.data);
                    if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'FINISH') {
                        waEmbeddedSessionInfo = {
                            phone_number_id: data.data.phone_number_id,
                            waba_id: data.data.waba_id,
                        };
                    }
                } catch (e) {
                    // Non-JSON postMessage from elsewhere on facebook.com - ignore.
                }
            });

            document.getElementById('whatsappEmbeddedSignupBtn')?.addEventListener('click', function() {
                waEmbeddedSessionInfo = {};

                FB.login(function(response) {
                    if (!response.authResponse || !response.authResponse.code) {
                        return; // user closed the popup or denied access
                    }

                    if (!waEmbeddedSessionInfo.phone_number_id) {
                        Swal.fire('Error', 'Signup completed but no phone number was returned. Please try again.', 'error');
                        return;
                    }

                    $.ajax({
                        url: "{{ route('admin.post-accounts.whatsapp.embedded') }}",
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                        },
                        data: {
                            code: response.authResponse.code,
                            phone_number_id: waEmbeddedSessionInfo.phone_number_id,
                            waba_id: waEmbeddedSessionInfo.waba_id,
                        },
                        success: function(res) {
                            Swal.fire('Success', res.message, 'success').then(() => location.reload());
                        },
                        error: function(xhr) {
                            Swal.fire('Error', xhr.responseJSON?.message || 'Failed to connect WhatsApp.', 'error');
                        }
                    });
                }, {
                    config_id: '{{ adminSetting('messaging.meta.whatsapp_config_id') }}',
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: {
                        setup: {},
                        featureType: '',
                        sessionInfoVersion: '3',
                    }
                });
            });
        @endif

        // ============================================
        // AI ASSISTANT (unchanged logic)
        // ============================================
        var AIAssistant = {
            generatedData: null,
            isProcessing: false,
            messages: null,
            input: null,
            sendBtn: null,
            mediaFile: null,
            isOpen: true,
            generatedMediaData: null,
            _appliedImageUrl: null,
            _isAIImageApplied: false,

            init: function() {
                this.messages = document.getElementById('aiMessages');
                this.input = document.getElementById('aiInput');
                this.sendBtn = document.getElementById('aiSendBtn');

                this.sendBtn.addEventListener('click', function() {
                    AIAssistant.sendMessage();
                });

                this.input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        AIAssistant.sendMessage();
                    }
                });

                document.querySelectorAll('.quick-chip').forEach(function(chip) {
                    chip.addEventListener('click', function() {
                        AIAssistant.input.value = this.dataset.prompt;
                        AIAssistant.sendMessage();
                    });
                });

                const aiMediaInput = document.getElementById('aiMediaInput');
                if (aiMediaInput) {
                    aiMediaInput.addEventListener('change', function(e) {
                        AIAssistant.handleMediaUpload(e);
                    });
                }

                this.input.focus();
            },

            handleMediaUpload: function(e) {
                const file = e.target.files[0];
                if (file) {
                    this.mediaFile = file;
                    const reader = new FileReader();

                    reader.onload = function(event) {
                        const preview = document.getElementById('aiMediaPreview');
                        const fileName = document.getElementById('mediaFileName');

                        if (preview) {
                            if (file.type.startsWith('video/')) {
                                preview.innerHTML =
                                    `<video src="${event.target.result}" style="max-width:80px; max-height:60px; border-radius:8px;" controls></video>`;
                            } else if (file.type.startsWith('image/')) {
                                preview.innerHTML =
                                    `<img src="${event.target.result}" style="max-width:80px; max-height:60px; border-radius:8px; object-fit:cover;" />`;
                            } else {
                                preview.innerHTML =
                                    `<span style="color:#4a5568; font-size:12px;">📎 ${file.name}</span>`;
                            }
                            preview.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            },

            sendMessage: function() {
                const prompt = this.input.value.trim();
                if (prompt && !this.isProcessing) {
                    this.generateContent(prompt);
                }
            },

            generateContent: function(prompt) {
                if (this.isProcessing) return;

                const welcome = document.getElementById('welcomeMsg');
                if (welcome) welcome.remove();

                this.addMessage('user', prompt);
                this.input.value = '';
                this.isProcessing = true;
                this.sendBtn.disabled = true;
                this.sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating...';

                const typingId = this.addTypingIndicator();
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

                let formData = new FormData();
                formData.append('prompt', prompt);

                const aiMediaInput = document.getElementById('aiMediaInput');
                if (aiMediaInput && aiMediaInput.files && aiMediaInput.files.length > 0) {
                    formData.append('media[]', aiMediaInput.files[0]);
                }

                const mainMediaInput = document.getElementById('mediaInput');
                console.log(mainMediaInput);
                if (mainMediaInput && mainMediaInput.files && mainMediaInput.files.length > 0) {
                    formData.append('media[]', mainMediaInput.files[0]);
                }

                // Replace with your actual AI generation route
                fetch("#", {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(response => {
                        this.removeTypingIndicator(typingId);

                        if (response.success && response.data) {
                            this.generatedData = response.data;
                            if (response.data.generated_image_url) {
                                this.generatedMediaData = response.data.generated_image_url;
                            }
                            this.displayGeneratedContent(response.data);
                        } else {
                            this.addMessage('bot', '❌ ' + (response.message || 'Failed to generate content.'));
                        }
                    })
                    .catch(error => {
                        this.removeTypingIndicator(typingId);
                        console.error('Error:', error);
                        this.addMessage('bot', '❌ Network error. Please check your connection.');
                    })
                    .finally(() => {
                        this.isProcessing = false;
                        this.sendBtn.disabled = false;
                        this.sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Generate';
                        this.scrollToBottom();
                    });
            },

            displayGeneratedContent: function(data) {
                $('#ai_media').val(data.generated_image_url);
                let html =
                    '<div style="font-weight: 700; font-size: 16px; margin-bottom: 6px;">✅ Content Generated!</div>';
                html +=
                    '<div style="font-size: 13px; color: #4a5568; margin-bottom: 12px;">Review and apply the suggestions below:</div>';

                if (data.generated_image_url) {
                    html += `<div class="suggestion-card" style="border-left-color: #48bb78;">
                        <div class="label">
                            <span>🖼️ Generated Image</span>
                            <span class="badge-type image-gen">AI GENERATED</span>
                        </div>
                        <div class="generated-image-preview">
                            <img src="${data.generated_image_url}" alt="Generated Image" id="aiGeneratedImage" 
                                    onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'color:#e53e3e; padding:10px;\\'>⚠️ Image could not be loaded</div>';" />
                        </div>
                        <div class="action-group" style="justify-content: center;">
                            <button class="action-btn primary sm" onclick="window.open('${data.generated_image_url}', '_blank')">
                                <i class="fas fa-external-link-alt"></i> View Full
                            </button>
                            <button class="action-btn success sm" onclick="AIAssistant.applyGeneratedImage('${data.generated_image_url}')">
                                <i class="fas fa-check"></i> Use in Post
                            </button>
                        </div>
                    </div>`;
                }

                if (data.title) {
                    html += `<div class="suggestion-card">
                        <div class="label">
                            <span>📌 Title</span>
                            <span class="badge-type title">TITLE</span>
                        </div>
                        <div class="value title-text">${this.escapeHtml(data.title)}</div>
                        <div class="action-group">
                            <button class="action-btn primary sm" data-type="title">
                                <i class="fas fa-check"></i> Apply Title
                            </button>
                        </div>
                    </div>`;
                }

                if (data.description) {
                    html += `<div class="suggestion-card">
                        <div class="label">
                            <span>📝 Description</span>
                            <span class="badge-type contents">CONTENT</span>
                        </div>
                        <div class="value">${this.escapeHtml(data.description)}</div>
                        <div class="action-group">
                            <button class="action-btn primary sm" data-type="description">
                                <i class="fas fa-check"></i> Apply Description
                            </button>
                        </div>
                    </div>`;
                }

                if (data.hashtags) {
                    html += `<div class="suggestion-card">
                        <div class="label">
                            <span>🏷️ Hashtags</span>
                            <span class="badge-type hashtags">HASHTAGS</span>
                        </div>
                        <div class="value hashtags-text">${this.escapeHtml(data.hashtags)}</div>
                        <div class="action-group">
                            <button class="action-btn primary sm" data-type="hashtags">
                                <i class="fas fa-check"></i> Apply Hashtags
                            </button>
                        </div>
                    </div>`;
                }

                if (data.media_type) {
                    const mediaIcon = data.media_type === 'image' ? '🖼️' : '🎬';
                    const badgeClass = data.media_type === 'image' ? 'image' : 'video';

                    html += `<div class="suggestion-card">
                        <div class="label">
                            <span>${mediaIcon} Media Suggestion</span>
                            <span class="badge-type media">MEDIA</span>
                        </div>
                        <div class="value">
                            <span class="media-badge ${badgeClass}">${data.media_type.toUpperCase()}</span>
                            ${data.media_description ? `<div style="margin-top: 4px; font-size: 13px; color: #4a5568;">${this.escapeHtml(data.media_description)}</div>` : ''}
                        </div>
                        <div class="action-group">
                            <button class="action-btn outline sm" data-type="media_prompt">
                                <i class="fas fa-copy"></i> Copy Prompt
                            </button>
                        </div>
                    </div>`;
                }

                html += `<div class="apply-all-container">
                    <button class="apply-all-btn" data-type="apply_all">
                        <i class="fas fa-check-double"></i> Apply All to Post
                    </button>
                </div>`;

                this.addMessage('bot', html);
            },

            addMessage: function(type, content) {
                const msgDiv = document.createElement('div');

                if (type === 'system') {
                    msgDiv.className = 'ai-msg system';
                    const bubble = document.createElement('div');
                    bubble.className = 'bubble';
                    bubble.innerHTML = content;
                    msgDiv.appendChild(bubble);
                    this.messages.appendChild(msgDiv);
                    this.scrollToBottom();
                    return;
                }

                msgDiv.className = `ai-msg`;

                const avatar = document.createElement('div');
                avatar.className = `avatar ${type === 'user' ? 'user' : 'bot'}`;
                avatar.textContent = type === 'user' ? 'You' : 'AI';

                const bubble = document.createElement('div');
                bubble.className = `bubble ${type === 'user' ? 'user' : 'bot'}`;
                bubble.innerHTML = content;

                msgDiv.appendChild(avatar);
                msgDiv.appendChild(bubble);
                this.messages.appendChild(msgDiv);
                this.scrollToBottom();
            },

            addTypingIndicator: function() {
                const id = 'typing-' + Date.now();
                const msgDiv = document.createElement('div');
                msgDiv.className = 'ai-msg';
                msgDiv.id = id;

                const avatar = document.createElement('div');
                avatar.className = 'avatar bot';
                avatar.textContent = 'AI';

                const bubble = document.createElement('div');
                bubble.className = 'bubble bot';
                bubble.innerHTML = `
                <div class="thinking-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            `;

                msgDiv.appendChild(avatar);
                msgDiv.appendChild(bubble);
                this.messages.appendChild(msgDiv);
                this.scrollToBottom();

                return id;
            },

            removeTypingIndicator: function(id) {
                const el = document.getElementById(id);
                if (el) el.remove();
            },

            scrollToBottom: function() {
                this.messages.scrollTop = this.messages.scrollHeight;
            },

            escapeHtml: function(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },

            applyContent: function(type) {
                if (!this.generatedData) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Content',
                        text: 'Please generate content first.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    return;
                }

                if (type === 'apply_all') {
                    this.applyAllContent();
                    return;
                }

                if (type === 'media_prompt') {
                    this.showMediaPrompt();
                    return;
                }

                this.applySingleContent(type);
            },

            applySingleContent: function(type) {
                const contentArea = document.getElementById('postContent');
                if (!contentArea) return;

                const value = this.generatedData[type];
                if (!value) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Content',
                        text: `No ${type} found to apply.`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    return;
                }

                const typeLabels = {
                    title: 'Title',
                    description: 'Description',
                    hashtags: 'Hashtags'
                };

                switch (type) {
                    case 'title':
                        contentArea.value = `✨ ${value}\n\n${contentArea.value}`;
                        break;
                    case 'description':
                        if (contentArea.value.length < 50) {
                            contentArea.value = value;
                        } else {
                            contentArea.value = contentArea.value + '\n\n' + value;
                        }
                        break;
                    case 'hashtags':
                        contentArea.value = contentArea.value + '\n\n' + value;
                        break;
                }

                contentArea.dispatchEvent(new Event('input'));

                Swal.fire({
                    icon: 'success',
                    title: 'Applied!',
                    text: `${typeLabels[type] || type} added to your post.`,
                    timer: 1200,
                    showConfirmButton: false
                });
            },

            applyAllContent: function() {
                const contentArea = document.getElementById('postContent');
                if (!contentArea) return;

                let current = contentArea.value;
                let applied = [];

                if (this.generatedData.title) {
                    current = `✨ ${this.generatedData.title}\n\n${current}`;
                    applied.push('Title');
                }

                if (this.generatedData.description) {
                    if (current.length < 50) {
                        current = this.generatedData.description;
                    } else {
                        current = current + '\n\n' + this.generatedData.description;
                    }
                    applied.push('Description');
                }

                if (this.generatedData.hashtags) {
                    current = current + '\n\n' + this.generatedData.hashtags;
                    applied.push('Hashtags');
                }

                contentArea.value = current;
                contentArea.dispatchEvent(new Event('input'));

                if (this.generatedData.generated_image_url) {
                    this.applyGeneratedImageToPreview(this.generatedData.generated_image_url);
                    this.applyImageToMediaInput(this.generatedData.generated_image_url);
                }

                Swal.fire({
                    icon: 'success',
                    title: 'All Applied! 🎉',
                    html: `Applied: <strong>${applied.join(', ')}</strong><br><small>${this.generatedData.generated_image_url ? '✨ Image also applied to preview' : ''}</small>`,
                    timer: 2500,
                    showConfirmButton: false
                });
            },

            applyGeneratedImageToPreview: function(imageSrc) {
                const previewImage = document.getElementById('previewImage');
                const previewVideo = document.getElementById('previewVideo');
                const placeholder = document.getElementById('previewPlaceholder');

                if (previewImage) {
                    previewImage.src = imageSrc;
                    previewImage.style.display = 'block';
                    if (previewVideo) previewVideo.style.display = 'none';
                    if (placeholder) placeholder.style.display = 'none';
                }
            },

            applyImageToMediaInput: function(imageSrc) {
                const mainMediaInput = document.getElementById('mediaInput');
                const aiMediaInput = document.getElementById('aiMediaInput');

                this._appliedImageUrl = imageSrc;
                this._isAIImageApplied = true;

                const hiddenInput = document.getElementById('ai_image_url');
                if (hiddenInput) {
                    hiddenInput.value = imageSrc;
                }

                this.clearMediaPreview();

                const previewWrapper = document.getElementById('mediaPreviewWrapper');
                const previewFileName = document.getElementById('mediaPreviewFileName');
                if (previewWrapper && previewFileName) {
                    previewWrapper.style.display = 'block';
                    previewFileName.textContent = 'AI Generated Image (URL)';
                }

                // Show thumbnail for AI image
                this.updateMediaThumbnail(imageSrc, 'image');

                const aiPreview = document.getElementById('aiMediaPreview');
                if (aiPreview) {
                    aiPreview.innerHTML = `
                        <img src="${imageSrc}" style="max-width:80px; max-height:60px; border-radius:8px; object-fit:cover;" 
                                onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=\\'color:#e53e3e; font-size:11px;\\'>⚠️ Image URL saved</span>';" />
                    `;
                    aiPreview.style.display = 'block';
                }

                this.applyGeneratedImageToPreview(imageSrc);
                this.applyPlatformRestrictions();
                this.fetchImageViaXHR(imageSrc);
            },

            clearMediaPreview: function() {
                const previewWrapper = document.getElementById('mediaPreviewWrapper');
                const previewFileName = document.getElementById('mediaPreviewFileName');
                const aiPreview = document.getElementById('aiMediaPreview');
                const aiMediaInput = document.getElementById('aiMediaInput');
                const mainMediaInput = document.getElementById('mediaInput');
                const fileNameDisplay = document.getElementById('mediaFileName');

                if (mainMediaInput) {
                    mainMediaInput.value = '';
                    mainMediaInput.files = null;
                }
                if (aiMediaInput) {
                    aiMediaInput.value = '';
                    aiMediaInput.files = null;
                }
                if (previewWrapper) previewWrapper.style.display = 'none';
                if (aiPreview) {
                    aiPreview.innerHTML = '';
                    aiPreview.style.display = 'none';
                }
                if (fileNameDisplay) fileNameDisplay.textContent = '';
                if (previewFileName) previewFileName.textContent = 'File uploaded';

                // Reset thumbnail
                this.resetMediaThumbnail();
            },

            resetMediaThumbnail: function() {
                const thumbContainer = document.getElementById('mediaPreviewThumb');
                const thumbImage = document.getElementById('thumbImage');
                const thumbVideo = document.getElementById('thumbVideo');
                const overlay = document.getElementById('thumbOverlay');

                if (thumbContainer) thumbContainer.classList.add('d-none');
                if (thumbImage) { thumbImage.src = ''; thumbImage.classList.add('d-none'); }
                if (thumbVideo) { thumbVideo.src = ''; thumbVideo.classList.add('d-none'); }
                if (overlay) overlay.classList.add('d-none');
            },

            updateMediaThumbnail: function(src, type) {
                const thumbContainer = document.getElementById('mediaPreviewThumb');
                const thumbImage = document.getElementById('thumbImage');
                const thumbVideo = document.getElementById('thumbVideo');
                const overlay = document.getElementById('thumbOverlay');

                if (!thumbContainer) return;

                thumbContainer.classList.remove('d-none');

                if (type === 'image') {
                    thumbImage.src = src;
                    thumbImage.classList.remove('d-none');
                    thumbVideo.classList.add('d-none');
                    overlay.classList.add('d-none');
                } else if (type === 'video') {
                    thumbVideo.src = src;
                    thumbVideo.classList.remove('d-none');
                    thumbImage.classList.add('d-none');
                    overlay.classList.remove('d-none');
                }
            },

            applyPlatformRestrictions: function() {
                const videoPlatforms = ['tiktok', 'youtube'];
                videoPlatforms.forEach(function(platform) {
                    const platformCard = document.querySelector(
                        `.platform-card-wrapper[data-platform="${platform}"]`);
                    if (platformCard) {
                        platformCard.classList.add('platform-disabled');
                        const platformCheckbox = document.getElementById(`platformCheckbox_${platform}`);
                        if (platformCheckbox) {
                            platformCheckbox.disabled = true;
                            platformCheckbox.checked = false;
                        }
                        const pageCheckboxes = platformCard.querySelectorAll('input[type="checkbox"]');
                        pageCheckboxes.forEach(function(cb) {
                            cb.disabled = true;
                            cb.checked = false;
                        });
                        const badge = document.getElementById(`restrictionBadge_${platform}`);
                        if (badge) badge.style.display = 'inline-block';
                    }
                });
                Swal.fire({
                    icon: 'info',
                    title: 'Platform Restrictions Applied',
                    text: 'TikTok and YouTube have been disabled because AI-generated images are not supported.',
                    timer: 4000,
                    showConfirmButton: true
                });
            },

            removePlatformRestrictions: function() {
                const videoPlatforms = ['tiktok', 'youtube'];
                videoPlatforms.forEach(function(platform) {
                    const platformCard = document.querySelector(
                        `.platform-card-wrapper[data-platform="${platform}"]`);
                    if (platformCard) {
                        platformCard.classList.remove('platform-disabled');
                        const platformCheckbox = document.getElementById(`platformCheckbox_${platform}`);
                        if (platformCheckbox) platformCheckbox.disabled = false;
                        const pageCheckboxes = platformCard.querySelectorAll('input[type="checkbox"]');
                        pageCheckboxes.forEach(function(cb) {
                            cb.disabled = false;
                        });
                        const badge = document.getElementById(`restrictionBadge_${platform}`);
                        if (badge) badge.style.display = 'none';
                    }
                });
            },

            fetchImageViaXHR: function(imageSrc) {
                const mainMediaInput = document.getElementById('mediaInput');
                const xhr = new XMLHttpRequest();
                xhr.open('GET', imageSrc, true);
                xhr.responseType = 'blob';
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const blob = this.response;
                            const ext = blob.type.split('/')[1] || 'png';
                            const fileName = `ai-generated-image.${ext}`;
                            const file = new File([blob], fileName, { type: blob.type });
                            if (mainMediaInput) {
                                const dataTransfer = new DataTransfer();
                                dataTransfer.items.add(file);
                                mainMediaInput.files = dataTransfer.files;
                                const event = new Event('change', { bubbles: true });
                                mainMediaInput.dispatchEvent(event);
                            }
                            const aiMediaInput = document.getElementById('aiMediaInput');
                            if (aiMediaInput) {
                                const dataTransfer = new DataTransfer();
                                dataTransfer.items.add(file);
                                aiMediaInput.files = dataTransfer.files;
                            }
                            const previewFileName = document.getElementById('mediaPreviewFileName');
                            if (previewFileName) {
                                previewFileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                            }
                        } catch (error) {
                            console.warn('Failed to create File from blob:', error);
                        }
                    }
                };
                xhr.onerror = function() { console.warn('XHR fetch failed, using URL fallback'); };
                xhr.send();
            },

            applyGeneratedImage: function(imageSrc) {
                Swal.fire({
                    title: 'Loading Image...',
                    text: 'Preparing the image for your post.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                this.applyGeneratedImageToPreview(imageSrc);
                this.applyImageToMediaInput(imageSrc);
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Image Applied! ✅',
                    text: 'The AI-generated image is ready for your post. TikTok and YouTube have been disabled.',
                    timer: 3000,
                    showConfirmButton: false
                });
            },

            showMediaPrompt: function() {
                const prompt = this.generatedData?.media_prompt;
                if (!prompt) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Prompt',
                        text: 'No image prompt available.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    return;
                }
                Swal.fire({
                    title: '🎨 Image Prompt',
                    html: `
                    <div style="text-align: left;">
                        <p class="text-secondary small">Copy this prompt for AI image generation:</p>
                        <textarea id="promptCopyText" class="form-control" rows="4">${this.escapeHtml(prompt)}</textarea>
                        <button class="btn btn-primary mt-2" onclick="AIAssistant.copyPromptText()">
                            <i class="fas fa-copy"></i> Copy to Clipboard
                        </button>
                    </div>
                `,
                    confirmButtonText: 'Close',
                });
            },

            copyPromptText: function() {
                const textarea = document.getElementById('promptCopyText');
                if (textarea) {
                    textarea.select();
                    navigator.clipboard.writeText(textarea.value).then(() => {
                        Swal.fire({ icon: 'success', title: 'Copied!', timer: 1000, showConfirmButton: false });
                    }).catch(() => {
                        document.execCommand('copy');
                        Swal.fire({ icon: 'success', title: 'Copied!', timer: 1000, showConfirmButton: false });
                    });
                }
            }
        };

        // ============================================
        // CLEAR ALL MEDIA (extended for thumbnail)
        // ============================================
        function clearAllMedia() {
            if (AIAssistant) {
                AIAssistant.clearMediaPreview();
                AIAssistant._appliedImageUrl = null;
                AIAssistant._isAIImageApplied = false;
                AIAssistant.removePlatformRestrictions();
                const hiddenInput = document.getElementById('ai_image_url');
                if (hiddenInput) hiddenInput.value = '';
                const aiMediaHidden = document.getElementById('ai_media');
                if (aiMediaHidden) aiMediaHidden.value = '';
                AIAssistant.resetMediaThumbnail();
            }
            const previewImage = document.getElementById('previewImage');
            const previewVideo = document.getElementById('previewVideo');
            const placeholder = document.getElementById('previewPlaceholder');
            if (previewImage) { previewImage.src = ''; previewImage.style.display = 'none'; }
            if (previewVideo) { previewVideo.src = ''; previewVideo.style.display = 'none'; }
            if (placeholder) placeholder.style.display = 'flex';
            const aiMediaPreview = document.getElementById('aiMediaPreview');
            if (aiMediaPreview) { aiMediaPreview.innerHTML = ''; aiMediaPreview.style.display = 'none'; }
            document.getElementById('mediaFileName').textContent = '';
            const previewWrapper = document.getElementById('mediaPreviewWrapper');
            if (previewWrapper) previewWrapper.style.display = 'none';
        }

        // ============================================
        // CONTAINER TOGGLE
        // ============================================
        function toggleAIContainer() {
            const body = document.getElementById('aiBody');
            const icon = document.getElementById('aiToggleIcon');
            const text = document.getElementById('aiToggleText');
            if (body.classList.contains('active')) {
                body.classList.remove('active');
                icon.className = 'fas fa-chevron-down';
                text.textContent = 'Expand';
            } else {
                body.classList.add('active');
                icon.className = 'fas fa-chevron-up';
                text.textContent = 'Collapse';
                setTimeout(function() { AIAssistant.input.focus(); }, 300);
            }
        }

        // ============================================
        // INIT
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            AIAssistant.init();

            document.addEventListener('click', function(e) {
                const btn = e.target.closest('[data-type]');
                if (btn) {
                    e.preventDefault();
                    AIAssistant.applyContent(btn.dataset.type);
                }
            });
        });

        // ============================================
        // PLATFORM FUNCTIONS
        // ============================================
        function togglePlatformPages(platform) {
            const container = document.getElementById('platformPages_' + platform);
            if (container) container.classList.toggle('d-none');
        }

        function togglePlatformSelection(platform, isChecked) {
            const container = document.getElementById('platformPages_' + platform);
            if (container) {
                const checkboxes = container.querySelectorAll('input[type="checkbox"][name*="[pages]"]');
                checkboxes.forEach(cb => cb.checked = isChecked);
                if (isChecked) container.classList.remove('d-none');
                else container.classList.add('d-none');
            }

            if (platform === 'whatsapp') {
                const fields = document.getElementById('whatsappBroadcastFields');
                if (fields) fields.classList.toggle('d-none', !isChecked);
            }
        }

        // ============================================
        // CHARACTER COUNTER & PREVIEW (enhanced with thumbnail)
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const contentInput = document.getElementById('postContent');
            const previewText = document.getElementById('previewText');
            const charCount = document.getElementById('charCount');

            if (contentInput && previewText && charCount) {
                contentInput.addEventListener('input', function() {
                    previewText.innerText = this.value || 'Start typing...';
                    charCount.innerText = this.value.length;
                });
            }

            // Media preview with thumbnail
            const mediaInput = document.getElementById('mediaInput');
            const previewWrapper = document.getElementById('mediaPreviewWrapper');
            const previewFileName = document.getElementById('mediaPreviewFileName');
            const previewFileSize = document.getElementById('mediaPreviewFileSize');
            const thumbContainer = document.getElementById('mediaPreviewThumb');
            const thumbImage = document.getElementById('thumbImage');
            const thumbVideo = document.getElementById('thumbVideo');
            const thumbOverlay = document.getElementById('thumbOverlay');

            if (mediaInput) {
                mediaInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    const img = document.getElementById('previewImage');
                    const video = document.getElementById('previewVideo');
                    const placeholder = document.getElementById('previewPlaceholder');

                    // Reset AI image state if user uploads a file manually
                    if (file && AIAssistant) {
                        AIAssistant._appliedImageUrl = null;
                        AIAssistant._isAIImageApplied = false;
                        AIAssistant.removePlatformRestrictions();
                        const hiddenInput = document.getElementById('ai_image_url');
                        if (hiddenInput) hiddenInput.value = '';
                        const aiMediaHidden = document.getElementById('ai_media');
                        if (aiMediaHidden) aiMediaHidden.value = '';
                    }

                    if (!file) {
                        // No file selected – reset everything
                        if (img) img.style.display = 'none';
                        if (video) video.style.display = 'none';
                        if (placeholder) placeholder.style.display = 'flex';
                        if (previewWrapper) previewWrapper.style.display = 'none';
                        AIAssistant.resetMediaThumbnail();
                        return;
                    }

                    // Show wrapper and file info
                    if (previewWrapper) previewWrapper.style.display = 'block';
                    if (previewFileName) previewFileName.textContent = file.name;
                    if (previewFileSize) previewFileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';

                    const url = URL.createObjectURL(file);
                    if (file.type.startsWith('image/')) {
                        // Disable TikTok/YouTube for images
                        const tiktokCard = document.querySelector('.platform-card-wrapper[data-platform="tiktok"]');
                        const youtubeCard = document.querySelector('.platform-card-wrapper[data-platform="youtube"]');
                        if (tiktokCard) {
                            tiktokCard.classList.add('platform-disabled');
                            document.getElementById('restrictionBadge_tiktok').style.display = 'inline-block';
                        }
                        if (youtubeCard) {
                            youtubeCard.classList.add('platform-disabled');
                            document.getElementById('restrictionBadge_youtube').style.display = 'inline-block';
                        }   
                        // Update preview card
                        if (img) { img.src = url; img.style.display = 'block'; $('#previewImage').removeClass('d-none');}
                        if (video) video.style.display = 'none'; $('#previewVideo').addClass('d-none');
                        if (placeholder) placeholder.style.display = 'none';

                        // Update upload thumbnail
                        AIAssistant.updateMediaThumbnail(url, 'image');
                    } else if (file.type.startsWith('video/')) {
                        // Enable TikTok/YouTube for videos
                        const tiktokCard = document.querySelector('.platform-card-wrapper[data-platform="tiktok"]');
                        const youtubeCard = document.querySelector('.platform-card-wrapper[data-platform="youtube"]');
                        if (tiktokCard) {
                            tiktokCard.classList.remove('platform-disabled');
                            document.getElementById('restrictionBadge_tiktok').style.display = 'none';
                        }
                        if (youtubeCard) {
                            youtubeCard.classList.remove('platform-disabled');
                            document.getElementById('restrictionBadge_youtube').style.display = 'none';
                        }

                        // Update preview card
                        if (video) { video.src = url; video.style.display = 'block'; $('#previewVideo').removeClass('d-none');}
                        if (img) img.style.display = 'none'; $('#previewImage').addClass('d-none');
                        if (placeholder) placeholder.style.display = 'none';

                        // Update upload thumbnail
                        AIAssistant.updateMediaThumbnail(url, 'video');
                    }
                });
            }

            // Schedule & Expiry Toggles
            const scheduleSwitch = document.getElementById('schedule_mode');
            const scheduleInput = document.querySelector('input[name="schedule_at"]');
            if (scheduleSwitch && scheduleInput) {
                if (!scheduleSwitch.checked) scheduleInput.style.display = 'none';
                scheduleSwitch.addEventListener('change', function() {
                    scheduleInput.style.display = this.checked ? 'block' : 'none';
                    if (!this.checked) scheduleInput.value = '';
                });
            }

            const expirySwitch = document.getElementById('expiry_mode');
            const expiryInput = document.querySelector('input[name="expiry_at"]');
            if (expirySwitch && expiryInput) {
                if (!expirySwitch.checked) expiryInput.style.display = 'none';
                expirySwitch.addEventListener('change', function() {
                    expiryInput.style.display = this.checked ? 'block' : 'none';
                    if (!this.checked) expiryInput.value = '';
                });
            }
        });

        // ============================================
        // CATEGORY SAVE (BS5)
        // ============================================
        $(document).ready(function() {
            $('#saveCategoryBtn').on('click', function() {
                const name = $('#name').val();
                const description = $('#description').val();
                if (!name || !description) {
                    alert('Please enter both category name and description');
                    return;
                }

                $.ajax({
                    url: "{{ route('admin.categories.store') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        name: name,
                        description: description
                    },
                    success: function(res) {
                        if (res.success) {
                            $('#category_id').append(
                                `<option value="${res.data.id}" selected>${res.data.name}</option>`
                            );
                            const modalEl = document.getElementById('addCategoryModal');
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            modal.hide();
                            $('#name').val('');
                            $('#description').val('');
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            const errors = xhr.responseJSON.errors;
                            $.each(errors, function(field, messages) {
                                $('.error-' + field).text(messages[0]);
                            });
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Please fix the highlighted errors.'
                            });
                        }
                    }
                });
            });

            // ============================================
            // FORM SUBMIT (unchanged)
            // ============================================
            $('#post').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);

                const aiImageUrl = document.getElementById('ai_image_url');
                if (aiImageUrl && aiImageUrl.value) formData.append('ai_image_url', aiImageUrl.value);

                const aiMedia = document.getElementById('ai_media');
                if (aiMedia && aiMedia.value) formData.append('ai_media', aiMedia.value);

                const aiFile = document.getElementById('aiMediaInput');
                if (aiFile && aiFile.files.length > 0) {
                    formData.append('ai_media_file', aiFile.files[0]);
                }


                const storeUrl = "{{ route('admin.posts.store') }}";

                $.ajax({
                    url: storeUrl,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message || 'Post saved successfully.',
                                timer: 3000,
                                showConfirmButton: true
                            }).then(() => {
                                if (response.redirect_url) window.location.href = response.redirect_url;
                            });
                            return;
                        }
                        // Error handling
                        if (response.errors) {
                            let errorHtml = '<ul>';
                            $.each(response.errors, function(key, val) {
                                errorHtml += `<li>${val[0]}</li>`;
                            });
                            errorHtml += '</ul>';
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: errorHtml
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'An error occurred.'
                            });
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            const errors = xhr.responseJSON.errors;
                            let errorHtml = '<ul>';
                            $.each(errors, function(key, val) {
                                errorHtml += `<li>${val[0]}</li>`;
                            });
                            errorHtml += '</ul>';
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: errorHtml
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An unexpected error occurred. Please try again.'
                            });
                        }
                    }
                });
            });
        });
    </script>
@endpush