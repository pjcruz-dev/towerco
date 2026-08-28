/**
 * Drop a leading markdown H1 so the page/drawer title is not duplicated.
 */
export function stripLeadingMarkdownH1(body: string): string {
  return body.replace(/^\s*#\s+[^\n]+\n+/, "");
}
