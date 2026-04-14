# Git Setup

## Local repository path

Your local repository path is:

```bash
G:\My Drive\CelestialWebDevelopment\git_repo\lunar_phase_plugin
```

## Initialize the repository

Open **Git Bash** and run:

```bash
cd "/g/My Drive/CelestialWebDevelopment/git_repo/lunar_phase_plugin"
git init
git branch -M main
git config user.name "john8bit"
```

## Create the GitHub repository

Create a new empty GitHub repository under the `john8bit` account. Suggested repository name:

```text
celestial-lunar-phase
```

Do not initialize it with a README, .gitignore, or license if you plan to push this package as-is.

## Connect local repo to GitHub

Replace the URL below with your final repository URL if it differs:

```bash
git remote add origin https://github.com/john8bit/celestial-lunar-phase.git
```

## First commit and push

```bash
git add .
git commit -m "Initial commit: Celestial Lunar Phase Widget repository"
git push -u origin main
```

## Future updates

```bash
git add .
git commit -m "Describe your change"
git push
```
