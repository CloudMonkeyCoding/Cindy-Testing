# Repository Instructions

- The `Sample/` directory contains reference assets only. Automated tests must exercise the live application served from `http://localhost:3000`, not the static samples.
- UI end-to-end tests live under `src/test/java/qa/cindys/ui`. Prefer explicit waits over implicit waits and skip tests gracefully when the target page is unreachable.
