#!/bin/bash

echo "🔍 Validating PixelPapa Plugin PHP Syntax..."
echo "=============================================="

cd "$(dirname "$0")" || exit 1

errors=0
total=0

# Find all PHP files except vendor
for file in $(find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*'); do
    total=$((total + 1))
    result=$(php -l "$file" 2>&1)
    
    if [[ $? -ne 0 ]]; then
        echo ""
        echo "❌ ERROR in: $file"
        echo "$result"
        errors=$((errors + 1))
    fi
done

echo ""
echo "=============================================="
echo "📊 Summary:"
echo "   Files checked: $total"
echo "   Errors found:  $errors"
echo ""

if [[ $errors -eq 0 ]]; then
    echo "✅ All PHP files are valid!"
    
    # Check for macOS junk files
    junk=$(find . -name '._*' -o -name '.DS_Store' | wc -l)
    if [[ $junk -gt 0 ]]; then
        echo "⚠️  Warning: Found $junk macOS hidden files"
        echo "   Run: find . -name '._*' -delete -o -name '.DS_Store' -delete"
    fi
    
    exit 0
else
    echo "❌ Fix errors before deployment!"
    exit 1
fi
