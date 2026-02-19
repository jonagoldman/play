 #!/bin/bash
 
 INPUT=$(cat)
 FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')
 
 if [[ "$FILE_PATH" =~ \.(js|ts|vue)$ ]]; then
   cd "$CLAUDE_PROJECT_DIR" && npx eslint --fix "$FILE_PATH" 2>/dev/null
 elif [[ "$FILE_PATH" =~ \.php$ ]]; then
   cd "$CLAUDE_PROJECT_DIR" && vendor/bin/pint "$FILE_PATH" 2>/dev/null
   cd "$CLAUDE_PROJECT_DIR" && vendor/bin/phpstan analyse --memory-limit=2G "$FILE_PATH" 2>/dev/null
 fi
 
 exit 0
