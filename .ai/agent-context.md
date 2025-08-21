# Agent Context

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Proxy Block** module for Drupal 10/11 - a contributed module that provides a block plugin capable of rendering any other block plugin in the system. This enables dynamic block selection and configuration through an administrative interface, primarily designed for A/B testing scenarios.

The module is part of the A/B Testing ecosystem and integrates with the [A/B Tests](https://www.github.com/Lullabot/ab_tests) project.

## Agent Strategies - Task Routing System

### Primary Task Router

When receiving a user request, analyze it for both explicit keywords and contextual intent to route to the appropriate specialized agent(s). You can delegate multiple tasks in parallel to different agents or multiple instances of the same agent.

### Agent Selection Matrix

#### 1. **task-orchestrator** (Primary Command Executor)

**Keywords**: run, execute, test, build, compile, lint, check, validate, cache, drush, composer, npm, phpunit, command
**Context Triggers**:

- Any request to execute commands or scripts
- Running tests or quality checks
- Building or compiling code
- Cache operations
- Command discovery from documentation

**Examples**:

- "Run the tests for proxy_block"
- "Clear the cache and run phpcs"
- "Execute the build pipeline"

#### 2. **drupal-backend-expert** (PHP/Drupal Development)

**Keywords**: module, plugin, entity, service, hook, api, database, query, field, formatter, controller, form, config, schema
**Context Triggers**:

- PHP code implementation
- Drupal API usage
- Database operations
- Backend architecture decisions
- Module development

**Examples**:

- "Create a custom field formatter"
- "Implement a new block plugin"
- "Optimize this database query"

#### 3. **drupal-frontend-specialist** (Theming/Frontend)

**Keywords**: theme, template, twig, css, javascript, component, sdc, styling, responsive, accessibility, markup, sass
**Context Triggers**:

- Frontend development
- Theming and templating
- CSS/JS issues
- Component creation
- Accessibility improvements

**Examples**:

- "Create a card component"
- "Fix mobile navigation styling"
- "Convert patterns to SDCs"

#### 4. **testing-qa-engineer** (Test Development)

**Keywords**: test, phpunit, coverage, assert, mock, fixture, browser, selenium, behat, functional, unit, kernel, playwright, e2e
**Context Triggers**:

- Writing new tests
- Debugging test failures
- Improving test coverage
- Test strategy development
- Playwright/E2E test work

**Special Note**: When working with Playwright tests, this agent MUST consult `/var/www/html/web/modules/contrib/proxy_block/tests/e2e/CLAUDE.md` for Drupal-specific testing methodology and proven patterns.

**Examples**:

- "Write tests for the new plugin"
- "Debug flaky browser tests"
- "Create test coverage for this module"
- "Fix Playwright test failures"

#### 5. **git-github-specialist** (Git & GitHub Operations)

**Keywords**: git, github, commit, push, pull, branch, merge, rebase, pr, pull request, issue, gh, clone, checkout, stash, log, diff, status, remote, origin, upstream
**Context Triggers**:

- All Git version control operations
- GitHub repository management
- Commit message creation following conventional commit standards
- Pull request creation and management
- Issue creation and tracking
- GitHub Actions monitoring and artifact management
- Branch management and merging strategies
- Repository analysis and history exploration

**Special Capabilities**:
- Analyzes past commit history to derive proper conventional commit format
- Strictly avoids any AI attribution in commits, PRs, or any repository metadata
- Deep expertise with `gh` CLI for comprehensive GitHub operations
- Handles complex Git workflows including rebasing, cherry-picking, and conflict resolution

**Examples**:
- "Create a pull request for this feature"
- "Commit these changes with proper conventional commit format"
- "Check the status of GitHub Actions for this PR"
- "Download artifacts from the latest CI run"
- "Create an issue to track this bug"
- "Analyze the commit history to understand recent changes"

#### 6. **devops-infrastructure-engineer** (Infrastructure/DevOps)

**Keywords**: docker, kubernetes, ci/cd, pipeline, deploy, infrastructure, ansible, terraform, monitoring, performance
**Context Triggers**:

- Infrastructure setup
- Deployment configuration
- CI/CD pipeline work
- Performance optimization
- System administration

**Examples**:

- "Set up CI pipeline"
- "Configure deployment automation"
- "Optimize container performance"

### Parallel Task Delegation Strategy

When a request contains multiple distinct tasks:

1. **Decompose** the request into independent subtasks
2. **Match** each subtask to the most appropriate agent(s)
3. **Delegate** tasks in parallel when they don't depend on each other
4. **Coordinate** results from multiple agents if needed

**Example Multi-Agent Request**:
User: "I need to create a new block plugin with tests and then deploy it to staging"

- Route to `drupal-backend-expert`: Create block plugin
- Route to `testing-qa-engineer`: Write comprehensive tests
- Route to `devops-infrastructure-engineer`: Configure deployment
- Route to `task-orchestrator`: Execute deployment commands

### Routing Decision Tree

```
1. Analyze request for Git/GitHub operations
   ├─ Yes → git-github-specialist
   └─ No → Continue analysis

2. Analyze request for command execution needs
   ├─ Yes → task-orchestrator (may delegate after execution)
   └─ No → Continue analysis

3. Identify primary domain
   ├─ Backend/PHP → drupal-backend-expert
   ├─ Frontend/Theme → drupal-frontend-specialist
   ├─ Testing → testing-qa-engineer
   ├─ Infrastructure → devops-infrastructure-engineer
   └─ Multiple → Parallel delegation

4. Check for follow-up needs
   └─ Delegate additional tasks as needed
```

### Default Behavior

- When in doubt, start with **task-orchestrator** for command-based tasks
- For code implementation without commands, route to the domain expert
- Always consider parallel delegation for complex multi-part requests
- Maintain context between agent handoffs for coherent responses

## Common Development Commands

**Note**: For command execution and discovery, delegate to the **task-orchestrator** agent, which maintains a comprehensive list of all project commands and will execute them appropriately.

## Code Architecture

**Note**: For detailed architecture, implementation patterns, and technical specifications, delegate to the **drupal-backend-expert** agent.

## Key Files

- **Main Implementation**: `src/Plugin/Block/ProxyBlock.php`
- **Module Definition**: `proxy_block.info.yml`
- **Tests**: `tests/src/` (Unit, Kernel, Functional, FunctionalJavascript)
- **E2E Tests**: `tests/e2e/` (Unit, Kernel, Functional, FunctionalJavascript)
- **Configuration**: Various config files for tools (phpstan.neon, phpunit.xml.dist, etc.)

For detailed file structure and architecture, consult the appropriate specialized agent.

## Development Workflow & Tools

**Note**: For development workflows, code quality commands, and tool configurations, delegate to the **task-orchestrator** agent for command execution or the appropriate specialized agent for implementation.

## Quick Reference

### Performance & Security

- Performance optimizations are built into the module architecture
- Security follows Drupal best practices
- For detailed implementation analysis, consult the **drupal-backend-expert** agent

## Inter-Agent Task Delegation Framework

### Delegation Principles

Specialized agents should **proactively delegate** subtasks to other agents when they encounter work outside their core expertise. This enables seamless multi-agent workflows and prevents agents from attempting tasks they're not optimized for.

### Delegation Decision Matrix

| Current Agent                      | Delegation Trigger                 | Target Agent              | Example Scenario                                                   |
| ---------------------------------- | ---------------------------------- | ------------------------- | ------------------------------------------------------------------ |
| **testing-qa-engineer**            | Test reveals code bug/issue        | **drupal-backend-expert** | "Test failing due to incorrect method signature in ProxyBlock.php" |
| **testing-qa-engineer**            | Need to run test commands          | **task-orchestrator**     | "Run PHPUnit with specific flags for this module"                  |
| **testing-qa-engineer**            | Need to commit test changes        | **git-github-specialist** | "Commit new test files with proper commit message"                 |
| **drupal-backend-expert**          | Need to execute commands           | **task-orchestrator**     | "Clear cache after code changes"                                   |
| **drupal-backend-expert**          | Code changes need tests            | **testing-qa-engineer**   | "Created new method, need unit test coverage"                      |
| **drupal-backend-expert**          | Need to commit code changes        | **git-github-specialist** | "Commit new feature with conventional commit format"               |
| **drupal-frontend-specialist**     | Need backend API changes           | **drupal-backend-expert** | "Component needs new entity field"                                 |
| **drupal-frontend-specialist**     | Need to run build commands         | **task-orchestrator**     | "Compile SCSS and run JS linting"                                  |
| **drupal-frontend-specialist**     | Need to commit frontend changes    | **git-github-specialist** | "Commit styling updates and component changes"                     |
| **devops-infrastructure-engineer** | Need application-specific commands | **task-orchestrator**     | "Deploy using project-specific scripts"                            |
| **devops-infrastructure-engineer** | Need to manage deployment branches | **git-github-specialist** | "Create release branch and tag version"                            |
| **task-orchestrator**              | Need Git/GitHub operations         | **git-github-specialist** | "Create PR after running successful tests"                         |
| **Any Agent**                      | Command execution needed           | **task-orchestrator**     | "Run any bash command or script"                                   |
| **Any Agent**                      | Git/GitHub operations needed       | **git-github-specialist** | "Any version control or GitHub repository task"                    |

### Delegation Protocol

When delegating, agents should:

1. **Recognize the need**: Identify when a subtask falls outside their expertise
2. **Select the right agent**: Use the delegation matrix to choose the appropriate specialist
3. **Provide context**: Include relevant background about the current task
4. **Request specific action**: Be clear about what needs to be done
5. **Coordinate results**: Integrate the delegated work back into their main task

### Delegation Syntax

```markdown
I need to delegate this subtask to [TARGET_AGENT]:

**Context**: [Brief background about current work]
**Delegation**: [Specific task to delegate]
**Expected outcome**: [What should be returned]
**Integration**: [How this fits back into main task]
```

### Example Delegation Flows

#### Scenario 1: QA Engineer finds code bug

```
testing-qa-engineer working on test → discovers bug in ProxyBlock.php → delegates to drupal-backend-expert → receives fix → updates test to verify fix
```

#### Scenario 2: Backend Expert needs tests

```
drupal-backend-expert implements new feature → delegates test creation to testing-qa-engineer → receives comprehensive tests → continues with development
```

#### Scenario 3: Any agent needs commands

```
[any-agent] working on task → needs to run commands → delegates to task-orchestrator → receives command results → continues with task
```

#### Scenario 4: Any agent needs Git/GitHub operations

```
[any-agent] completes work → needs to commit/push/create PR → delegates to git-github-specialist → receives confirmation → task complete
```

#### Scenario 5: QA Engineer creates tests and commits

```
testing-qa-engineer writes tests → delegates to git-github-specialist for commit → receives proper commit → continues with additional test work
```

## Important Reminders

- **ALWAYS** remember to lint the code base before pushing code (use **task-orchestrator** to execute linting commands)
- **ALWAYS** delegate Git and GitHub operations to **git-github-specialist** - never handle version control directly
- Route tasks to the most appropriate specialized agent based on the task routing system above
- Consider parallel delegation for complex multi-part requests
- **PROACTIVELY DELEGATE** when work falls outside your core expertise - don't attempt everything yourself
- **Git/GitHub Priority**: Any mention of commits, PRs, branches, or GitHub operations should immediately route to **git-github-specialist**
