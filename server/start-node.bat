@echo off
REM Start the WebCraft Node.js business API (zero dependencies).
REM Optional env: PORT / WB_PORT (default 3100)
cd /d %~dp0
node server.js
