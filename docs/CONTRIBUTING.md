# Contribution Guidelines

Thank you for your interest in contributing! To maintain a structured and efficient workflow, please follow these guidelines when making contributions.

## Issue Tracking and Pull Requests

- All contributions must start with an issue unless explicitly approved otherwise.
- Contributors must create an issue before opening a pull request (PR).
- Each PR should reference a Jira ticket in its branch name (see **Branching Strategy** below).
- PRs targeting `main`, `dev`, or `release/*` branches require at least **one approval** before merging.

## Branching Strategy

We follow **GitFlow** with protected branches. The branching strategy will be additionally verified as a workflow to ensure compliance.

We follow **GitFlow** with protected branches:

- Protected branches: `main`, `dev`, `release/*`
- Branch naming convention:
    - **Feature branches**: `feature/KAN-###-description`
    - **Bugfix branches**: `bugfix/KAN-###-description`
    - **Hotfix branches**: `hotfix/KAN-###-description`
    - **Release branches**: `release/x.y.z`

Example:

```
feature/KAN-123-add-login
hotfix/KAN-456-fix-auth-token
release/0.1.0
```

## Commit Messages

- Follow **Conventional Commits** format:
    - `feat: KAN-123 add authentication method`
    - `fix: KAN-456 resolve login bug`
    - `docs: KAN-789 update README`

## Pull Request Titles

- Include the ticket number at the beginning:
    - `KAN-123 Implement user authentication`
    - `KAN-456 Fix login bug causing crashes`

## Code Style and Linting

- In the future, **linting** will be enforced as a **CI/CD step**.
- Ensure your code follows the style guidelines before submitting a PR.

## Testing Requirements

- In the future, **unit tests will be required** and enforced as a **CI/CD step**.
- PRs should include tests when adding new features or modifying existing functionality.

## Development Environment Setup

- **Docker setup** is required for development.
- Ensure your local environment matches the project setup.

## Merging and Reviews

- PRs to `main`, `dev`, and `release/*` branches **must** be reviewed and approved by at least **one reviewer**.
- Pull requests should be merged only by the assignee.
- Contributors should **avoid direct commits** to protected branches.

## Versioning Policy

- We follow **incremental versioning**, starting from **0.1**.
- Version **1.0** will be considered the first **public release**.

## Communication

- Discussions happen on **Slack**.
- For any questions, reach out via Slack channels or comments on issues/PRs.

---

By contributing, you agree to follow these guidelines to maintain code quality and workflow consistency. Thank you for your contributions! 🚀
