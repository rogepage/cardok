#!/usr/bin/env python3
import json
import os
import sys

def main():
    if len(sys.argv) < 2:
        return

    req_id = sys.argv[1]
    log_path = "monolith/storage/logs/laravel.log"

    if not os.path.exists(log_path):
        return

    with open(log_path, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            if req_id not in line:
                continue
            try:
                d = json.loads(line)
            except Exception:
                continue

            event = d.get("event")
            prov = str(d.get("provider", "")).upper()
            att = d.get("attempt")

            if event == "provider.request":
                print(f"  [{prov}] attempt {att}")
            elif event == "vehicle_provider_failed":
                if d.get("is_fatal_for_provider"):
                    print(f"  [{prov}] unavailable")
                else:
                    err = d.get("error")
                    print(f"  [{prov}] attempt {att} failed: {err}")
            elif event == "provider.retry":
                backoff = d.get("backoff_ms")
                max_att = d.get("max_attempts")
                print(f"  [{prov}] retry backoff {backoff}ms (tentativa {att}/{max_att})")
            elif event == "provider.fallback":
                p_from = str(d.get("from", "")).upper()
                p_to = str(d.get("to", "")).upper()
                print(f"  => [FALLBACK] {p_from} -> {p_to}")
            elif event == "provider.response" and d.get("status") == "success":
                count = d.get("debts_count")
                dur = d.get("duration_ms")
                print(f"  [{prov}] success ({count} débitos recebidos em {dur}ms)")

if __name__ == "__main__":
    main()
