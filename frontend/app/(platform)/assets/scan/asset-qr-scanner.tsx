"use client";

import { Html5Qrcode } from "html5-qrcode";
import { useEffect, useRef } from "react";

export function AssetQrScanner({ onScan }: { onScan: (value: string) => void }) {
  const regionId = "asset-qr-region";
  const scannerRef = useRef<Html5Qrcode | null>(null);

  useEffect(() => {
    let disposed = false;
    const scanner = new Html5Qrcode(regionId);
    scannerRef.current = scanner;

    const release = () => {
      try {
        if (!scanner.isScanning) {
          return;
        }
        void scanner.stop().then(() => scanner.clear()).catch(() => undefined);
      } catch {
        // stop() throws when the camera never reached a running state.
      }
    };

    void scanner
      .start(
        { facingMode: "environment" },
        { fps: 8, qrbox: { width: 220, height: 220 } },
        (text) => onScan(text),
        () => undefined,
      )
      .then(() => {
        if (disposed) {
          release();
        }
      })
      .catch(() => undefined);

    return () => {
      disposed = true;
      release();
    };
  }, [onScan]);

  return (
    <div
      id={regionId}
      className="min-h-[220px] overflow-hidden rounded-xl border border-border bg-muted"
    />
  );
}
