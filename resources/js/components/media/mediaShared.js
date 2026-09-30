// Shared by MediaGallery.vue, MediaPicker.vue, MediaInputAttach.vue and the
// posts composer's MediaGrid.vue.

// Order/keys match App\Support\MediaCompatibility::PLATFORMS.
export const PLATFORM_META = {
  facebook:  { name: 'Facebook',  icon: 'bxl-facebook-circle', color: '#1877F2' },
  instagram: { name: 'Instagram', icon: 'bxl-instagram',       color: '#E1306C' },
  tiktok:    { name: 'TikTok',    icon: 'bxl-tiktok',          color: '#111827' },
  snapchat:  { name: 'Snapchat',  icon: 'bxl-snapchat',        color: '#E5B800' },
  x:         { name: 'X',         icon: null,                  color: '#111827' },
  linkedin:  { name: 'LinkedIn',  icon: 'bxl-linkedin-square', color: '#0A66C2' },
  youtube:   { name: 'YouTube',   icon: 'bxl-youtube',         color: '#FF0000' },
  google:    { name: 'Google',    icon: 'bxl-google',          color: '#4285F4' },
};

// Client-side pre-check only, for instant feedback - the server
// (MediaAssetService) re-validates everything from the file's bytes.
export const ACCEPT = '.jpg,.jpeg,.png,.gif,.webp,.mp4,.m4v,.mov,.webm,image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm';
const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const VIDEO_EXT = ['mp4', 'm4v', 'mov', 'webm'];
const MAX_BYTES = { image: 30 * 1024 * 1024, video: 500 * 1024 * 1024 };

export function precheck(file) {
  const ext = (file.name.split('.').pop() || '').toLowerCase();
  const type = IMAGE_EXT.includes(ext) ? 'image' : VIDEO_EXT.includes(ext) ? 'video' : null;

  if (!type) return `"${file.name}" isn't a supported format. Use JPG, PNG, GIF, WEBP, MP4, MOV, M4V or WEBM.`;
  if (file.size > MAX_BYTES[type]) return `"${file.name}" is ${formatBytes(file.size)} - ${type}s can be up to ${formatBytes(MAX_BYTES[type])}.`;
  if (!file.size) return `"${file.name}" is empty.`;
  return null;
}

export function formatBytes(bytes) {
  if (!bytes && bytes !== 0) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
}

export function formatDuration(seconds) {
  if (!seconds && seconds !== 0) return '';
  const s = Math.round(seconds);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = String(s % 60).padStart(2, '0');
  return h ? `${h}:${String(m).padStart(2, '0')}:${sec}` : `${String(m).padStart(2, '0')}:${sec}`;
}

export function mediaInfo(asset) {
  return [
    formatBytes(asset.size),
    asset.width && asset.height ? `${asset.width} × ${asset.height}` : null,
    asset.type === 'video' && asset.duration ? `${Math.round(asset.duration)}s` : null,
  ].filter(Boolean).join(' · ');
}

/** Compatible / needs-adjustment split for a gallery asset. */
export function compatSummary(asset, platforms = Object.keys(PLATFORM_META)) {
  const compat = asset.compatibility || {};
  const bad = platforms.filter(p => compat[p] && !compat[p].ok);
  return { ok: platforms.filter(p => compat[p]?.ok), bad, allOk: bad.length === 0 };
}

export function issuesText(asset, platform) {
  return (asset.compatibility?.[platform]?.issues || []).join(' ');
}

export function errorMessage(error, fallback) {
  const data = error?.response?.data;
  if (error?.response?.status === 413) return 'The file is larger than the server accepts.';
  return data?.errors?.file?.[0] || Object.values(data?.errors || {}).flat()[0] || data?.message || fallback;
}

/**
 * Poster frame for a video, captured in the browser (no server-side
 * ffmpeg dependency). Resolves null on any failure - a missing thumbnail
 * never blocks an upload.
 */
export function captureVideoThumbnail(file) {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file);
    const video = document.createElement('video');
    let done = false;
    const finish = (blob) => {
      if (done) return;
      done = true;
      URL.revokeObjectURL(url);
      resolve(blob);
    };

    video.muted = true;
    video.playsInline = true;
    video.preload = 'metadata';
    video.src = url;
    setTimeout(() => finish(null), 8000);

    video.addEventListener('loadedmetadata', () => {
      video.currentTime = Math.min(1, (video.duration || 2) / 4);
    });
    video.addEventListener('seeked', () => {
      try {
        const scale = Math.min(1, 640 / (video.videoWidth || 640));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round((video.videoWidth || 640) * scale);
        canvas.height = Math.round((video.videoHeight || 360) * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => finish(blob), 'image/jpeg', 0.82);
      } catch (e) {
        finish(null);
      }
    });
    video.addEventListener('error', () => finish(null));
  });
}

/** Upload one file to the gallery. Resolves with the created asset. */
export async function uploadToGallery(storeUrl, file, onProgress) {
  const body = new FormData();
  body.append('file', file);

  if ((file.type || '').startsWith('video/') || /\.(mp4|m4v|mov|webm)$/i.test(file.name)) {
    const thumb = await captureVideoThumbnail(file);
    if (thumb) body.append('thumbnail', thumb, 'thumbnail.jpg');
  }

  const { data } = await window.axios.post(storeUrl, body, {
    headers: { 'Content-Type': 'multipart/form-data', Accept: 'application/json' },
    onUploadProgress: e => onProgress && e.total && onProgress(Math.round((e.loaded / e.total) * 100)),
  });

  return data.media;
}

export function fetchGallery(indexUrl, params) {
  return window.axios.get(indexUrl, {
    params,
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
  }).then(({ data }) => data.media);
}
