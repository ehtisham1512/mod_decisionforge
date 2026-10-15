# Decision Forge

Decision Forge is a scenario assessment and branching simulation activity for Moodle. It assesses what a learner does as a situation develops—not only whether they can recognise a correct answer.

Version 2.2 provides two compatible experience modes and optional AI-assisted authoring workflows:

- **Immersive simulation:** a connected operational route containing evidence, prioritisation, decisions, changing conditions and reflective records.
- **Quick decision check:** the original independent mini-case experience for short scenario assessments.
- **AI Builders:** teacher-controlled generators that convert one expert brief into either a validated immersive simulation or a set of editable quick decision cases.

## AI-assisted authoring

- Dedicated premium builders for both immersive simulations and Quick Decision Checks.
- Guided simulation controls for interactions, difficulty, branching, evidence and indicators.
- Guided Quick Assessment controls for language, difficulty, case and action counts, points, unsafe choices and feedback detail.
- Selectable OpenAI Responses or Google Gemini GenerateContent provider with structured JSON output; no browser-side API calls or exposed credentials.
- Provider choices identify the exact model and whether the active credential comes from the server environment or administrator settings; secret key characters are never displayed.
- Guided output-language choices for English, Spanish, French, Russian, German, Italian, Portuguese, Arabic, Chinese, Japanese or a custom language.
- Complete Moodle interface language packs for English, Spanish and Russian; Moodle switches all activity, authoring, learner, report, administration, privacy and AI Builder strings through the normal site/user language controls.
- Author-only preview before generated content enters a live assessment.
- Defensive server validation of text, points, ranks, evidence, choice quality, attainable full marks and routes.
- Quick drafts append by default; replacement requires explicit confirmation and preserves cases referenced by learner attempts.
- Simulation replacement also requires explicit confirmation; every import uses an all-or-nothing database transaction.
- Per-author rate limits, configurable timeout and an audit record covered by Moodle's Privacy API.
- Learner names, answers, attempts and grades are never sent for generation.

## Immersive simulation

- Connected stages with explicit per-action branches or automatic sequential routing.
- Briefing, evidence, priority ordering, decision and reflection interaction types.
- Consequences that change safety, compliance, trust and resource indicators.
- Route-specific scoring: learners are graded only against scored stages they actually encounter.
- Immutable attempt snapshots that preserve the exact content and branches seen by each learner.
- A visual authoring canvas with route health warnings and published/draft state.
- A command-room learner workspace, consequence reveals and detailed after-action report.
- An editable reference simulation demonstrating a medication incident with genuine route divergence.

## Quick decision checks

- Any number of independent mini-cases, up to the configurable site limit.
- Up to four actions per case with weighted 0%, 25%, 50%, 75% or 100% quality scores.
- Optional image, rich scenario, decision question and consequence for every action.
- Immediate or deferred consequence feedback.
- Optional case and action shuffling with a stable per-attempt order.
- Optional reasoning and confidence fields.
- Time windows, time limit and configurable attempts.
- Highest, average or last-attempt grading.
- Moodle Gradebook and standard activity completion integration.
- Responsive learner, authoring, result and report interfaces.
- Teacher case ordering, duplication, activation, deletion and editable sample cases.
- Historical case version preservation after learners have attempted a case.
- Attempt review and secure teacher deletion with grade recalculation.
- Privacy API, events, calendar, course reset and backup/restore support.

## Requirements

- Moodle 4.5 LTS through Moodle 5.2.
- PHP and database versions supported by the corresponding Moodle release.
- The core activity has no CDN or paid dependency. The optional AI Builder requires an OpenAI or Google Gemini API project and key; normal provider usage charges may apply.

## Installation

1. Extract the package into `mod/decisionforge`.
2. Sign in as a site administrator.
3. Open **Site administration > Notifications**.
4. Complete the installation.
5. Add **Decision Forge** from a course activity chooser.

To enable AI authoring, open **Site administration > Plugins > Activity modules >
Decision Forge**, enable AI authoring, choose a supported model and
configure at least one provider. Teachers can choose OpenAI or Gemini when both
are available. For production, the recommended key sources are the server
environment variables `DECISIONFORGE_OPENAI_API_KEY` and
`DECISIONFORGE_GEMINI_API_KEY`; they override saved settings and keep credentials
outside Moodle configuration storage. The teacher provider selector shows the
provider, exact model alias and active credential source. The validated draft
preview records the same non-secret information used for that generation.

When upgrading from an earlier release, replace the complete `mod/decisionforge`
folder and open **Site administration > Notifications**. Existing activities
remain in Quick Decision Check mode. The upgrade queues Gradebook repair for cron
after Moodle releases the upgrade lock; it never calls Gradebook APIs inside the
database upgrade transaction.

## Teacher workflow

1. Create the activity and choose **Immersive simulation** or **Quick decision check**.
2. For a simulation, open **Simulation Studio** and build with AI, add stages manually, or install the editable reference simulation.
3. When using AI, enter a non-confidential expert brief, select the guided controls, generate, and review the complete draft before importing it.
4. Connect default routes and, for decision stages, route individual actions to different stages.
5. Set action quality, consequences and indicator changes; then resolve every health warning.
6. Preview the experience and complete every meaningful branch before learners begin.

Quick Decision Check authors use **Manage cases** and can select **Create with AI**, create cases manually, or install the three editable samples. AI-generated cases remain fully editable after import.

For pass-based completion, set a **Grade to pass**, then under **Activity
completion** select **Student must receive a grade** and **Student must receive a
passing grade**. Teachers can open Moodle's Gradebook directly from the activity,
case manager or attempt report.

Once a case has been used in an attempt, editing it creates a new live version and archives the previous version. This protects the integrity of historical attempt reviews.

## Security design

- Every mutating request requires an authenticated session and Moodle session key.
- Capabilities are checked on every page and action endpoint.
- Attempt ownership is checked before learner access.
- Selected choices, evidence and priority orders are verified against the learner's current stage snapshot.
- Scores and consequences are calculated from database records on the server.
- Quality values, correct ranks and metric impacts are never included in the learner page.
- Unique database constraints prevent duplicate quick responses and duplicate simulation sequence entries.
- Simulation routes are resolved on the server and capped at 50 transitions per attempt.
- Moodle APIs handle files, formatting, database access and output escaping.
- Uploaded images are stored in Moodle's managed file pool and served through `pluginfile.php` after access checks.
- The AI credential is used only by Moodle's server-side HTTP client and is never rendered into a page or JavaScript.
- AI output is treated as untrusted input, converted to plain text and imported only after strict schema and business-rule validation.
- OpenAI uses strict structured outputs. Gemini uses the core GenerateContent JSON mode with the full contract supplied as prompt text; both paths pass through the same strict local blueprint and business-rule validation before import.
- OpenAI and Gemini requests use `store: false` and contain the author brief, course/activity names and generation controls—not learner responses, attempts or grades.

## Accessibility

- Semantic forms, fieldsets, labels, headings and native radio controls.
- Complete keyboard access without drag-and-drop.
- Visible focus states and error messages.
- Reduced-motion and forced-colour support.
- Responsive layouts for desktop, tablet and mobile.
- Progressive enhancement: the assessment remains functional if JavaScript fails.

## License

GNU GPL v3 or later.
