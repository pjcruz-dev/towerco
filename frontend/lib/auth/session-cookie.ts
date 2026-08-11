const cookieName = "toweros_session";
const cookieMaxAgeSeconds = 60 * 60 * 24 * 30;

export function setSessionCookie() {
  document.cookie = `${cookieName}=1; path=/; max-age=${cookieMaxAgeSeconds}; SameSite=Lax`;
}

export function clearSessionCookie() {
  document.cookie = `${cookieName}=; Max-Age=0; path=/; SameSite=Lax`;
}

export function hasSessionCookie(): boolean {
  if (typeof document === "undefined") {
    return false;
  }

  return document.cookie.split(";").some((part) => part.trim().startsWith(`${cookieName}=`));
}
