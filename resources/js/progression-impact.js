/**
 * The programme form's progression panel: holds the selected rule, and — the moment the
 * admin picks "sequential" — fetches who that rule would have blocked.
 *
 * Deliberately lazy. The audit walks every live enrolment in the programme, so an admin who
 * opened this form to change a fee never pays for it. Fetched once and kept: flicking
 * between the two radios must not re-run the sweep.
 */
export default function progressionImpact({ rule, impactUrl }) {
    return {
        rule,
        state: 'idle', // idle | loading | ready | failed
        impact: null,
        expanded: false,

        init() {
            this.$watch('rule', () => this.load());
            this.load();
        },

        get sequential() {
            return this.rule === 'sequential';
        },

        load() {
            // No URL on the create form: a programme that does not exist yet has no
            // students to affect, so there is nothing to audit.
            if (!this.sequential || !impactUrl || this.state === 'loading' || this.state === 'ready') {
                return;
            }

            this.state = 'loading';

            fetch(impactUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    return response.json();
                })
                .then((data) => {
                    this.impact = data;
                    this.state = 'ready';
                })
                .catch(() => {
                    this.state = 'failed';
                });
        },

        retry() {
            this.state = 'idle';
            this.load();
        },

        /**
         * People first, enrolments second. "25 students are enrolled in 164 courses" reads
         * as though each of them holds 164, so the total is stated separately once there is
         * more than one student to spread it across.
         */
        get headline() {
            if (!this.impact || this.impact.blocked === 0) {
                return '';
            }

            const students = this.impact.students;
            const enrolments = this.impact.blocked;

            if (students === 1) {
                const what = enrolments === 1 ? 'a course' : `${enrolments} courses`;

                return `One student is already enrolled in ${what} this rule would block.`;
            }

            return `${students} students are already enrolled in courses this rule would block, ${enrolments} enrolments in all.`;
        },

        /**
         * The all-clear, said in a way that shows the work. "Nobody is affected" alone reads
         * as though nothing was looked at; naming the number checked is the difference
         * between a reassurance and an assertion.
         */
        get clearDetail() {
            if (!this.impact) {
                return '';
            }

            if (this.impact.checked === 0) {
                return 'This programme has no live enrolments yet.';
            }

            return this.impact.checked === 1
                ? 'Its one live enrolment would not have been blocked.'
                : `None of its ${this.impact.checked} live enrolments would have been blocked.`;
        },
    };
}
