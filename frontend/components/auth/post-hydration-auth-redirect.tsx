"use client";

/**
 * Previously auto-redirected /login → /dashboard when localStorage had a session.
 * That caused rapid full-page reload loops on non-local tenant hosts (middleware cookie
 * vs client session race), leaving the login page stuck before React effects could run.
 *
 * Successful sign-in already uses window.location.assign("/dashboard").
 * Stale sessions are cleared by auth-store hydrate + app-providers on protected routes.
 */
export function PostHydrationAuthRedirect() {
  return null;
}
