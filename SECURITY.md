# Security policy

## Supported versions

Security fixes are provided for the latest Decision Forge release on each supported Moodle branch.

## Reporting a vulnerability

Do not publish suspected vulnerabilities in a public issue. Use the private vulnerability-reporting channel configured in the source repository, or contact the maintainer through the Moodle.org profile linked from the plugin listing. Include the affected version, reproduction steps, impact, and any suggested mitigation. The maintainer should acknowledge the report promptly, validate it, prepare a coordinated fix, and credit the reporter when requested.

## Security design

- Capability and context checks guard every learner, authoring, reporting, and deletion endpoint.
- State-changing requests require a Moodle session key.
- Attempt ownership, current-stage membership, choice membership, evidence membership and priority permutations are validated server-side.
- Grades, consequences, route targets and indicator changes are calculated from immutable server-side snapshots, never from client-provided scores.
- Simulation routes are limited to 50 transitions to contain accidental authoring loops.
- Learner pages never receive quality values, correct priority ranks or configured metric impacts.
- Rich text is rendered through Moodle formatting APIs and uploads use Moodle's managed File API.
- SVG uploads are intentionally excluded; accepted case images are JPG, PNG, GIF, and WebP.
- Historical case versions become read-only once attempts depend on them.

## AI provider security

- AI authoring is disabled by default and restricted by the `mod/decisionforge:useai` capability.
- Provider calls are made only by Moodle's server-side HTTP client to the fixed OpenAI Responses endpoint or the Google Gemini `models.generateContent` endpoint for the validated configured model.
- The API key is never included in templates, JavaScript, generated content, events or application error text.
- The authoring interface reports only the credential source (server environment or administrator settings), never the key value, prefix, suffix or length.
- The administrative password fields prevent casual display, but Moodle configuration values are not encrypted at rest by this plugin. Production sites should prefer the `DECISIONFORGE_OPENAI_API_KEY` and `DECISIONFORGE_GEMINI_API_KEY` web-server environment variables and restrict process/configuration access.
- Prompts must not contain personal, sensitive or confidential data. The request includes authoring context only and sets `store: false`.
- Generated JSON is untrusted: all content is stripped to bounded plain text, routes are forced forward, score/rank/impact ranges are checked, and imports use a delegated database transaction.
- OpenAI uses provider-side strict schema enforcement. Gemini uses GenerateContent JSON response mode; every Gemini field, length, route, score, rank and impact is checked locally before import.
- Existing stages require an explicit replacement checkbox. Existing learner attempt snapshots and responses are not deleted by an AI import.
- Provider errors are mapped to safe Moodle messages; credentials and raw provider responses are not logged by the plugin.
- Invalid-key responses, including Gemini HTTP 400 responses, are classified without displaying or retaining the provider response body.
