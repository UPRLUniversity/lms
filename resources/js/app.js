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
import { assignmentSubmit } from './assignment-submit';
import certificateStatus from './certificate-status';
import { certificateTemplateEditor } from './certificate-template-editor';
import notificationBell from './notification-bell';

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

// Assignments: the student hand-in form (upload progress + draft autosave).
Alpine.data('assignmentSubmit', assignmentSubmit);

// Certificates: polls for the queued PDF render to finish (completion screen + My
// Certificates cards); a no-op when it's already ready.
Alpine.data('certificateStatus', certificateStatus);
Alpine.data('certificateTemplateEditor', certificateTemplateEditor);

// The topbar bell (Section 8): polls for unread count + recent items.
Alpine.data('notificationBell', notificationBell);

Alpine.start();

// Lazy-load TinyMCE only on pages that actually use a rich editor, keeping it out
// of the main bundle.
if (document.querySelector('[data-rich-editor]')) {
    import('./rich-editor');
}

// Lazy-load Chart.js (dashboards + report analytics) only where a chart renders.
if (document.querySelector('canvas[data-chart]')) {
    import('./charts').then(({ default: initCharts }) => initCharts());
}
