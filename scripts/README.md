# Git Hooks & Pre-Push Checks

This directory contains scripts for running quality checks before pushing code to the repository.

## Setup

After cloning the repository, run:

```bash
composer setup-hooks
```

Or manually:

```bash
bash scripts/setup-git-hooks.sh
```

This will install the pre-push git hook that automatically runs checks before you push.

## What Gets Checked

The pre-push hook runs the following checks:

1. **Code Style** - PHP CS Fixer checks for code formatting issues
2. **Static Analysis** - PHPStan checks for type errors and potential bugs
3. **Tests** - PHPUnit runs the test suite
4. **Example Files** - Validates syntax of all example PHP files

## Manual Checks

You can run all checks manually at any time:

```bash
composer check
```

This runs:
- Code style check (dry-run)
- PHPStan analysis
- Test suite

## Individual Commands

- `composer cs-fix` - Fix code style issues automatically
- `composer phpstan` - Run static analysis
- `composer test` - Run tests
- `composer check` - Run all checks

## Skipping Hooks (Not Recommended)

If you absolutely need to skip the pre-push checks (e.g., for emergency hotfixes), you can use:

```bash
git push --no-verify
```

**Warning**: Only skip hooks when absolutely necessary. The CI pipeline will still run these checks and may reject your push.

## Troubleshooting

### Hook not running

Make sure the hook is executable:
```bash
chmod +x .git/hooks/pre-push
```

### Hook fails but you need to push

1. Fix the issues (run `composer cs-fix` for style issues)
2. Run `composer check` to verify everything passes
3. Try pushing again

### Hook is too slow

The checks are designed to be fast. If they're slow:
- Make sure dependencies are installed (`composer install`)
- Check if PHPStan cache is enabled
- Consider running checks in parallel (future enhancement)

