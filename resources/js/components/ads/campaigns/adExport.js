// CSV download for the campaigns pages' Export button (AdReportController::export).
// Resolves to null on success, or the error message to show.
export async function downloadCsv(url, params, fallbackName, failedText) {
  try {
    const res = await window.axios.get(url, { params, responseType: 'blob' });
    const match = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] || '');
    const href = URL.createObjectURL(res.data);
    const a = document.createElement('a');
    a.href = href; a.download = match ? match[1] : fallbackName;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(href), 1000);
    return null;
  } catch (e) {
    try { const body = JSON.parse(await e.response.data.text()); return body.message || body.error || failedText; } catch (x) { return failedText; }
  }
}
