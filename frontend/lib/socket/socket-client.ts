"use client";

import { io, type Socket } from "socket.io-client";

let socket: Socket | null = null;

/** Realtime is optional in local dev — enable only when Soketi/Echo is running. */
export function isSocketEnabled(): boolean {
  return process.env.NEXT_PUBLIC_SOCKET_ENABLED === "true";
}

export function getSocket(): Socket {
  if (!socket) {
    socket = io(process.env.NEXT_PUBLIC_SOCKET_URL ?? "http://localhost:6001", {
      transports: ["websocket"],
      autoConnect: false,
      reconnection: isSocketEnabled(),
      reconnectionAttempts: isSocketEnabled() ? 3 : 0,
      reconnectionDelay: 3000,
    });
  }

  return socket;
}
