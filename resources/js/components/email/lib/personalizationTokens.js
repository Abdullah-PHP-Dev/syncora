// SendGrid itself substitutes these against each recipient's synced
// Contact fields at real send time (see SendGridContactService /
// EmailMarketingService's docblocks) - there is no local substitution
// engine and none is needed here. The editor only needs to let a seller
// insert the literal token text into a Text/Heading block.
export const PERSONALIZATION_TOKENS = [
  { label: 'First Name', token: '{{first_name}}' },
  { label: 'Last Name', token: '{{last_name}}' },
  { label: 'Email', token: '{{email}}' },
];

/**
 * Inserts `token` at the current caret position inside `el` (a
 * contenteditable element), falling back to appending at the end when the
 * element doesn't currently hold the selection (eg. the button was
 * clicked while focus was in the inspector panel, not the canvas).
 */
export function insertTokenAtCursor(el, token) {
  el.focus();

  const selection = window.getSelection();
  let range = null;

  if (selection && selection.rangeCount > 0) {
    const candidate = selection.getRangeAt(0);
    if (el.contains(candidate.commonAncestorContainer)) {
      range = candidate;
    }
  }

  if (!range) {
    range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
  }

  range.deleteContents();
  const node = document.createTextNode(token);
  range.insertNode(node);
  range.setStartAfter(node);
  range.setEndAfter(node);

  selection.removeAllRanges();
  selection.addRange(range);
}
