# Moodle Plugins directory release checklist

The code package includes two assessment experiences, dedicated optional AI Builders for simulations and quick assessments, a branching simulation engine, visual route authoring, GPL declarations, complete English, Spanish and Russian language packs, capabilities, Privacy API provider, backup and restore, events, Gradebook integration, standard completion support, course reset support, automated tests, changelog, and installation documentation.

## External service disclosure

AI authoring is disabled by default. An administrator can configure OpenAI, Google Gemini, or both. An authorised teacher explicitly chooses a configured provider. Requests are sent to one of these fixed endpoints:

- OpenAI: `https://api.openai.com/v1/responses`
- Google Gemini: `https://generativelanguage.googleapis.com/v1beta/models/{validated-model}:generateContent`

The following authoring data is sent:

- the teacher's expert brief;
- the course and activity names;
- selected language, difficulty, stage count, interaction types and generation controls.

Learner identities, responses, attempts and grades are not queried or sent. Requests set `store: false`. The selected provider's applicable service terms, privacy terms, retention controls, regional availability, charges and rate limits remain the site operator's responsibility. The Moodle Privacy API declares both the external transfer and stored teacher draft records.

Before submitting a release to the Moodle Plugins directory, the maintainer must complete the external publication steps that cannot be embedded in a ZIP:

1. Confirm that `mod_decisionforge` is available and reserve the component name.
2. Publish the source in a public Git repository with a public issue tracker.
3. Configure a private vulnerability-reporting channel in the repository.
4. Run the Moodle Plugin CI matrix and Code Checker against every declared Moodle branch and supported database.
5. Install and upgrade the ZIP on clean staging sites, then test AI configuration and failure modes, every simulation branch, Quick Decision Check compatibility, Gradebook and pass completion, backup/restore, cron, privacy requests, course reset, accessibility, and all supported roles.
6. Create accurate screenshots and a short description for the listing; do not use Moodle trademarks as part of the product name or branding.
7. Tag the exact release commit, generate the ZIP from that tag, upload it, and resolve every automated validation warning before requesting approval.

Marketplace approval is a review process; inclusion of this checklist does not claim that the plugin has already been approved.
