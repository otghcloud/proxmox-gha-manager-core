import $ from 'jquery';
import * as tabler from '@tabler/core/js/tabler';

import initDeleteModal from './base/modules/modal-delete';
import initLabelEditor from './base/modules/label-editor';
import initPostActions from './base/modules/post-action';
import initIsoPicker from './base/modules/iso-picker';
import initStoragePicker from './base/modules/storage-picker';
import initNodeStoragePicker from './base/modules/node-storage-picker';
import initTemplateTargetMappings from './base/modules/template-target-mappings';
import initPoolNodes from './base/modules/pool-nodes';
import initBuildLog from './base/modules/build-log';

window.$ = window.jQuery = $;
window.bootstrap = tabler.bootstrap;

initDeleteModal();
initLabelEditor();
initPostActions();
initIsoPicker();
initStoragePicker();
initNodeStoragePicker();
initTemplateTargetMappings();
initPoolNodes();
initBuildLog();
