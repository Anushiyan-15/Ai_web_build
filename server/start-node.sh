#!/bin/sh
# Start the WebCraft Node.js business API (zero dependencies).
cd "$(dirname "$0")"
exec node server.js
