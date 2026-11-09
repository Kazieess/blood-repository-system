// Public rendering receives only the explicit availability snapshot and demo labels.
import { state as store, roles } from './model.js';
export { bloodTypes } from './data.js';
export { roles };
export const state = {
  get publicSnapshot() { return store.publicSnapshot; },
  get publicTimestamp() { return store.publicTimestamp; },
  get publicMode() { return store.publicMode; },
};
