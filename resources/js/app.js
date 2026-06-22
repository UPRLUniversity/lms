import './bootstrap';
import './confirm'; // exposes window.uprlConfirm (branded SweetAlert2)

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import dataTable from './data-table';
import { courseBuilder, courseSettings, objectiveRows } from './course-builder';
import learnPlayer from './learn';
import { questionEditor } from './question-bank';
import { assessmentBuilder } from './assessment-builder';
import { attemptRunner } from './attempt';

window.Alpine = Alpine;

Alpine.plugin(collapse);

// Reusable live-table component (search/sort/filter/paginate without reloads).
Alpine.data('dataTable', dataTable);

// Course builder + its settings/objectives helpers.
Alpine.data('courseBuilder', courseBuilder);
Alpine.data('courseSettings', courseSettings);
Alpine.data('objectiveRows', objectiveRows);

// Learning player (sidebar curriculum, Complete & Continue, video resume).
Alpine.data('learnPlayer', learnPlayer);

// Assessments: the per-type question editor, the assessment builder, and the
// student taking engine (timer + autosave + progress map).
Alpine.data('questionEditor', questionEditor);
Alpine.data('assessmentBuilder', assessmentBuilder);
Alpine.data('attemptRunner', attemptRunner);

Alpine.start();

// Lazy-load TinyMCE only on pages that actually use a rich editor, keeping it out
// of the main bundle.
if (document.querySelector('[data-rich-editor]')) {
    import('./rich-editor');
}
