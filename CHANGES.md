# Change log

## 2.2.0 — 2026-08-12

- Added a dedicated AI Builder for Quick Decision Check assessments using OpenAI or Google Gemini.
- Added guided controls for language, difficulty, case count, action count, points, unsafe distractors and consequence detail.
- Added a premium teacher preview showing every case, action, quality score and consequence before import.
- Added a strict Quick Assessment JSON contract and defensive local validation, including attainable 100% actions.
- Added safe append-by-default import plus explicitly confirmed replacement that preserves learner-attempt history.
- Reused server-only credentials, provider visibility, rate limits, author-only drafts, events and Privacy API coverage.
- Added complete English, Spanish and Russian interface strings and regression coverage for contracts and imports.

## 2.1.8 — 2026-08-12

- Fixed edited Quick Assessment cases submitting their case ID as the Moodle course-module ID.
- Removed the ambiguous hidden `id` field from case authoring.
- Bound case submissions to an explicit action URL containing the verified course-module ID.
- Confirmed the stage editor and AI Builder do not share the case-record collision path.

## 2.1.7 — 2026-08-12

- Allowed media-only Quick Decision Check actions containing standard video, audio, image or embed elements.
- Kept Moodle's normal secure content cleaning in place for every rendered option.
- Added responsive learner styling for embedded media and Moodle media-filter output.
- Added regression coverage for iframe, video, audio and image-only editor values.

## 2.1.6 — 2026-08-12

- Fixed Quick Decision Check validation incorrectly treating completed rich-text actions as empty.
- Normalised array, object and scalar Moodle editor values consistently during validation and saving.
- Kept strict rejection of empty editor markup, non-breaking spaces and zero-width characters.
- Added regression coverage for supported editor representations and safe format handling.

## 2.1.5 — 2026-08-11

- Prevented timeout refreshes and learner submissions from racing against each other.
- Serialised each simulation attempt's writes with Moodle's cross-process lock API.
- Made repeated stage submissions idempotent by reopening the saved consequence or final report.
- Replaced learner-facing stale-attempt exception pages with safe informational redirects.

## 2.1.4 — 2026-08-11

- Replaced the Gemini Interactions request with the broadly supported `models.generateContent` JSON response mode.
- Supplies the complete blueprint contract as prompt text and retains strict server-side validation before import.
- Added a validated model-specific endpoint and a strict GenerateContent response parser for blocking and output-limit states.
- Added regression coverage for the Gemini JSON-mode request and response formats.

## 2.1.3 — 2026-08-11

- Removed unsupported string-length keywords from the Gemini structured-output schema.
- Removed the obsolete Gemini API revision header and added the documented output-token configuration.
- Added safe error classification for invalid Gemini keys, unavailable models and rejected structured requests.
- Added complete Spanish and Russian Moodle interface language packs for the entire activity.

## 2.1.2 — 2026-08-11

- Fixed Moodle administration validation for current OpenAI and Gemini model identifiers, including dotted and hyphenated aliases.
- Added safe fallback model aliases for installations where the displayed default has not yet been persisted.
- Added provider, model and non-secret credential-source visibility in the AI Builder and generated-draft review.
- Added model-identifier validation coverage and retained environment-variable precedence without exposing API keys.

## 2.1.1 — 2026-08-10

- Added selectable OpenAI and Google Gemini generation providers.
- Added separate provider credentials, models and environment-variable overrides.
- Replaced free-form language entry with guided multilingual generation choices, including English, Spanish, French and Russian.
- Added current Gemini Interactions API structured-output parsing and regression coverage.

## 2.1.0 — 2026-08-10

- Added a guided AI Simulation Builder powered by server-side OpenAI Structured Outputs.
- Added strict blueprint validation, author-only previews, rate limits and transactional import.
- Added an AI draft audit table, capability, event and Moodle Privacy API declarations.
- Added an environment-variable API key override and administrative cost controls.

## 2.0.3 — 2026-08-10

- Fixed evidence cards so opening and collapsing content cannot silently undo the reviewed state.
- Replaced the browser's hidden-checkbox validation message with a clear critical-evidence counter and inline guidance.
- Highlights unopened required cards while retaining server-side enforcement against incomplete submissions.

## 2.0.2 — 2026-08-10

- Fixed activity creation when Moodle submits an unset grade-to-pass value as `NULL`.
- Added defensive defaults and type normalization for every required optional activity setting.
- Added a regression test covering null Gradebook configuration.

## 2.0.1 — 2026-08-10

- Fixed activity settings and stage authoring forms on Moodle 5.0 by replacing the unsupported QuickForm `number` element.
- Added server-side bounds checking for simulation metrics, choice quality, priority ranks, impacts, and stage points.

## 2.0.0 — 2026-08-10

- Added a full branching simulation engine alongside the original Quick Decision Check mode.
- Added briefing, evidence review, prioritisation, consequential decision and professional reflection stages.
- Added a visual route studio with per-choice branching, published/draft stages and configuration health checks.
- Added live safety, compliance, trust and resource indicators that respond to server-validated decisions.
- Added immutable per-attempt simulation snapshots so later author edits cannot rewrite historical work.
- Added consequence reveals, route-aware scoring, outcome classifications and a premium after-action report.
- Added an editable hospital medication-incident reference simulation.
- Extended Gradebook, completion, reports, privacy, course reset and backup/restore support to simulation attempts.
- Added simulation engine PHPUnit coverage and hardened mode switching, branch validation and stage deletion.
- Preserved all existing Quick Decision Check activities and attempts without conversion.

## 1.0.4 — 2026-08-07

- Fixed `cannotexecduringupgrade` by moving Gradebook repair out of the database upgrade transaction.
- Existing grade items, grades and completion states are now repaired safely by a queued post-upgrade task.

## 1.0.3 — 2026-08-07

- Replaced custom grade text fields with Moodle's standard Gradebook integration, including grade category and grade-to-pass controls.
- Restored automatic completion based on receiving a grade or receiving a passing grade.
- Added an upgrade repair that creates missing Gradebook items, republishes existing grades and recalculates completion.
- Added the standard bulk `get_user_grades` callback and stable attempt timestamps for Gradebook synchronization.
- Added direct Gradebook access for teachers and clarified grade, point and pass-status labels throughout the interface.

## 1.0.2 — 2026-08-07

- Fixed case-authoring pages loading before Moodle's optional repository constants are available.
- Removed the unnecessary `FILE_INTERNAL` override; Moodle's file manager safely uses internal storage by default.

## 1.0.1 — 2026-08-07

- Fixed Moodle course-module compatibility when opening the activity after creation.
- The manager now accepts both `core_course\cm_info` and generated `stdClass` course-module records.

## 1.0.0 — 2026-08-07

- Initial stable release.
- Premium mini-case authoring, attempting, feedback, results and reporting.
- Weighted decisions, reasoning, confidence and shuffling.
- Gradebook, completion, calendar, events and course reset integration.
- Privacy API and backup/restore support.
- Responsive and accessible learner interface.
