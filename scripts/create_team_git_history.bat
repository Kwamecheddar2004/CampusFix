@echo off
REM ==============================================================================
REM  CampusFix - Windows Batch Wrapper to generate Multi-Member Git History
REM ==============================================================================
echo ==================================================================
echo  CampusFix Multi-Member Git History Generator
echo ==================================================================

REM Run PowerShell Script
powershell -ExecutionPolicy Bypass -File "%~dp0create_team_git_history.ps1"

pause

