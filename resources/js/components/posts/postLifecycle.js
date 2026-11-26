// SocialEaz-only lifecycle actions on one platform post (PostController::
// changeState): retry a failed post, cancel a schedule (kept as a draft),
// publish or schedule a draft. Which ones apply comes from the post's
// capabilities (can_retry / can_cancel / can_publish) - never guessed here.

function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

async function confirm(message) {
  if (window.Swal) {
    const { isConfirmed } = await window.Swal.fire({ icon: 'warning', html: esc(message), showCancelButton: true });
    return isConfirmed;
  }
  return window.confirm(message);
}

async function askTime(label) {
  const min = new Date(Date.now() + 15 * 60000);
  const pad = n => String(n).padStart(2, '0');
  const value = `${min.getFullYear()}-${pad(min.getMonth() + 1)}-${pad(min.getDate())}T${pad(min.getHours())}:${pad(min.getMinutes())}`;
  if (window.Swal) {
    const { value: picked } = await window.Swal.fire({ title: label, input: 'datetime-local', inputValue: value, showCancelButton: true });
    return picked || null;
  }
  return window.prompt(label, value) || null;
}

/**
 * @param {{ template: string, target: { post_id: number }, action: 'retry'|'cancel'|'publish'|'schedule', t: Function }} opts
 * @returns {Promise<{ success: boolean, message?: string, post?: object } | null>} null when the user backed out
 */
export async function changePostState({ template, target, action, t }) {
  const url = template.replace('__POST__', target.post_id) + '/state';
  const body = { action: action === 'schedule' ? 'publish' : action };

  if (action === 'schedule') {
    const at = await askTime(t('lifecycle.schedule_prompt'));
    if (!at) return null;
    body.schedule_at = at.replace('T', ' ') + ':00';
    body.timezone = (window.Intl && Intl.DateTimeFormat().resolvedOptions().timeZone) || '';
  }

  const send = () => window.axios.post(url, body, { headers: { Accept: 'application/json' } })
    .then(({ data }) => data)
    .catch(e => ((e.response && e.response.data) || { success: false, message: t('alerts.generic_error') }));

  let data = await send();
  // An interrupted publish may already be live: only resend once the seller confirms.
  if (!data.success && data.confirm) {
    if (!(await confirm(data.message))) return null;
    body.confirmed = true;
    data = await send();
  }

  return data;
}
