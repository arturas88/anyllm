#!/bin/bash

# Setup script to install git hooks
# Run this after cloning the repository to enable pre-push checks

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
HOOKS_DIR="$REPO_ROOT/.git/hooks"

echo "🔧 Setting up git hooks..."

# Check if we're in a git repository
if [ ! -d "$REPO_ROOT/.git" ]; then
    echo "❌ Error: Not a git repository"
    exit 1
fi

# Create hooks directory if it doesn't exist
mkdir -p "$HOOKS_DIR"

# Install pre-push hook
if [ -f "$SCRIPT_DIR/pre-push-check.sh" ]; then
    # Create symlink or copy the hook
    if [ -L "$HOOKS_DIR/pre-push" ] || [ -f "$HOOKS_DIR/pre-push" ]; then
        echo "⚠️  Pre-push hook already exists. Backing up..."
        mv "$HOOKS_DIR/pre-push" "$HOOKS_DIR/pre-push.backup"
    fi
    
    # Create the hook that calls our script
    cat > "$HOOKS_DIR/pre-push" << 'EOF'
#!/bin/bash
# Pre-push git hook
HOOK_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$HOOK_DIR/../.." && pwd)"
exec "$REPO_ROOT/scripts/pre-push-check.sh"
EOF
    
    chmod +x "$HOOKS_DIR/pre-push"
    echo "✅ Pre-push hook installed"
else
    echo "❌ Error: pre-push-check.sh not found"
    exit 1
fi

echo ""
echo "✨ Git hooks setup complete!"
echo ""
echo "The pre-push hook will now run checks before you push to the repository."
echo "To run checks manually, use: composer check"
echo "To skip hooks (not recommended), use: git push --no-verify"

