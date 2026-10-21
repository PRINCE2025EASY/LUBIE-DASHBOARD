#!/usr/bin/env bash
# Regenerate all eight .drawio files from the schema and the diagram scripts, then validate.
set -e
cd "$(dirname "$0")"
for s in d01_erd d02_structure d03_dfd d04_seq d05_act d06_state d07_comp d08_uc; do python3 "$s.py"; done
# Optional previews (needs: npm install mxgraph@4.2.2 playwright):
# for f in ../../docs/diagrams/*.drawio; do node render.js "$f" ../../docs/diagrams/previews; done
