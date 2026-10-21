# Push to GitHub (owner steps)

The project folder `C:\xampp\htdocs\lubie-dashboard` is already a git repository with two
branches (main and iteration-1) and the remote set to
https://github.com/PRINCE2025EASY/LUBIE-DASHBOARD.git. You sign in yourself; no password or
token goes into chat.

## Option A: Git for Windows (command line)

1. Install Git for Windows from https://git-scm.com/download/win if `git --version` fails.
2. Open a terminal and run:

       cd C:\xampp\htdocs\lubie-dashboard
       git status
       git push -u origin main
       git push -u origin iteration-1
       git tag -a v0.4.0 -m "Iteration 1 tested (Stage 4)"
       git push origin v0.4.0

3. The first push opens a GitHub sign-in window (Git Credential Manager). Sign in there.

`git status` must say "nothing to commit, working tree clean". `.env` and `vendor` stay on
your computer because `.gitignore` excludes them.

## Option B: GitHub Desktop

File > Add local repository > choose `C:\xampp\htdocs\lubie-dashboard` > Publish or Push
origin. Switch to iteration-1 and push it too.

## Option C: let Claude push

Add PRINCE2025EASY/LUBIE-DASHBOARD to this session's repository sources in the Claude app,
then say "push". Claude then pushes and checks the CI run.

## After the push

1. Open the Actions tab. The CI run must be green (both jobs).
2. Settings > Branches > add a rule for `main`: require a pull request and the "CI" checks.
3. When you make the repository private, the CI keeps working (free minutes apply).
