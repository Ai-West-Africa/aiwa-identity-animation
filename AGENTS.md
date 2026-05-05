# Agent Information

This file documents the automated agents, bots, and CI/CD processes used in
the **AiWA Identity Animation** project.

## Automated Agents

### Development Agents

- **Code Quality Agents** — PHPCS, ESLint, Stylelint linting on every push/PR
- **Security Scanning Agents** — CodeQL static analysis; GitHub secret scanning
- **Dependency Update Agents** — Dependabot for npm and Composer

### Testing Agents

- **Continuous Integration (CI)** — Lint workflow runs on every branch push and PR
- **Build Validation** — `npm run build` validated in CI to ensure block assets compile

### Deployment Agents

- **Build Agents** — `@wordpress/scripts` compiles block assets to `build/`
- **Release Management** — Tag-triggered workflow (`v*.*.*`) creates GitHub Release
  with versioned ZIP and SHA256 checksum

## Agent Configuration

Agents are configured to:

- Run automatically on pull requests and pushes
- Perform security scanning before merging
- Ensure code quality standards are met (PHP 7.4+, WordPress 6.3+)
- Validate that `npm run build` compiles successfully

## Agent Permissions

All agents operate with least-privilege principles:

- Read repository contents
- Run tests and builds
- Report results and create check statuses
- Update status on pull requests

Agents do **NOT** have permission to:

- Directly merge code
- Modify protected branches without human approval
- Access production credentials

## Adding New Agents

To add a new automated agent:

1. Document its purpose and scope in this file.
2. Define the required permissions and access levels.
3. Configure appropriate security controls.
4. Add the workflow YAML under `.github/workflows/`.

## Contact

For questions about agents and automation, open an issue or contact
**hello@aiwestafrica.com**.

---

Last Updated: May 2025
