import DataTable from 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';
import { buildGridConfig } from './grid-config.js';

export function initGrid(element, options) {
  return new DataTable(element, buildGridConfig(options));
}
