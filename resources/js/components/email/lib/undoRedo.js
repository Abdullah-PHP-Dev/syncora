const HISTORY_LIMIT = 50;
const TEXT_DEBOUNCE_MS = 500;

/**
 * Whole-tree snapshot undo/redo, not a diff/patch engine - email block
 * trees are small (a handful of sections/columns/blocks, not thousands of
 * nodes), so a full JSON.parse(JSON.stringify(state)) snapshot is a few
 * KB and trivially cheap. A diff engine would add real edge-case risk
 * (out-of-order undo across structural moves) for a workload where it
 * buys nothing.
 *
 * Snapshot triggers (this is what avoids "excessive duplicate states"):
 *   - push(state) for structural changes (add/remove/duplicate/move a
 *     block, add/remove a section/column) - call immediately, one push
 *     per action.
 *   - pushDebounced(state) for contenteditable text edits and inspector
 *     text/textarea fields - coalesces a whole typing burst into one
 *     undo step per 500ms pause, not one per keystroke.
 *   - Both are deduped against the last pushed snapshot (skipped if
 *     identical), so a blur-without-change or a duplicate move event
 *     never wastes a history slot.
 */
export function createHistory(initialState) {
  const past = [];
  const future = [];
  let current = clone(initialState);
  let debounceTimer = null;

  function clone(state) {
    return JSON.parse(JSON.stringify(state));
  }

  function push(state) {
    const snapshot = clone(state);
    if (JSON.stringify(snapshot) === JSON.stringify(current)) {
      return;
    }

    past.push(current);
    if (past.length > HISTORY_LIMIT) {
      past.shift();
    }
    current = snapshot;
    future.length = 0;
  }

  function pushDebounced(state) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => push(state), TEXT_DEBOUNCE_MS);
  }

  function undo() {
    if (past.length === 0) {
      return current;
    }
    future.unshift(current);
    current = past.pop();
    return clone(current);
  }

  function redo() {
    if (future.length === 0) {
      return current;
    }
    past.push(current);
    current = future.shift();
    return clone(current);
  }

  return {
    push,
    pushDebounced,
    undo,
    redo,
    canUndo: () => past.length > 0,
    canRedo: () => future.length > 0,
  };
}
