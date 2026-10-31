# Backup indexed retention and manifest reconciliation

Run 41168953058 on c842541 passed the corrupt-archive/drill cases, then failed the preexisting expired-backup refusal: the fixture expired the stored row but the restore plan read only the manifest. The admin/pruning date and restore date could disagree. No main deployment occurred.

Restore now validates the indexed date and requires that it represents the same UTC boundary as the manifest before authorization. It rejects malformed, expired-index/future-manifest, and future-index/expired-manifest mismatches without mutating state. The original expired-row assertion remains; native acceptance adds exact state retention plus a genuinely expired, consistent manifest that must reach the existing RestorePlan retention refusal. Pure real-policy checks also cover impossible dates, missing/array values and independence from the PHP server timezone. All10 pure policy assertions and284 preserved acceptance-harness assertions pass locally; real SQL/browser rerun remains required.

This does not extend retention, permit automatic database restore, remove the confirmation phrase or change private archive ownership. The delivered1.26.1 archives and main remain unchanged. Review is owner-authorized ChatGPT self-review, not independent audit approval.
