export type AssistantRouteContext = {
  moduleKey: string | null;
  pagePath: string;
  suggestedQuestions: string[];
};

const DEFAULT_SUGGESTIONS = [
  "How do I get started in INFRA SUITE?",
  "Why can’t I see a page or module?",
  "How do I create an E-Forms request?",
];

const ROUTE_RULES: Array<{
  match: RegExp;
  moduleKey: string;
  suggestions: string[];
}> = [
  {
    match: /^\/e-approval(\/|$)/,
    moduleKey: "e_approval",
    suggestions: [
      "How do I create an E-Forms request?",
      "How do I approve a request waiting for me?",
      "Where do I find my submissions?",
    ],
  },
  {
    match: /^\/ticketing(\/|$)/,
    moduleKey: "ticketing",
    suggestions: [
      "How do I create a ticket?",
      "What is the status of TKT-00001?",
      "Who can assign tickets?",
    ],
  },
  {
    match: /^\/dynamic-entities(\/|$)/,
    moduleKey: "dynamic_entities",
    suggestions: [
      "How do I open a Dynamic Entities pack?",
      "How do I create a new record?",
      "Where do I configure fields and layouts?",
    ],
  },
  {
    match: /^\/users(\/|$)/,
    moduleKey: "team_access",
    suggestions: [
      "How do I assign roles to a user?",
      "Why can’t a user see a module?",
    ],
  },
  {
    match: /^\/dashboard(\/|$)/,
    moduleKey: "core",
    suggestions: DEFAULT_SUGGESTIONS,
  },
];

export function resolveAssistantRouteContext(pathname: string | null | undefined): AssistantRouteContext {
  const pagePath = pathname && pathname !== "" ? pathname : "/dashboard";

  for (const rule of ROUTE_RULES) {
    if (rule.match.test(pagePath)) {
      return {
        moduleKey: rule.moduleKey,
        pagePath,
        suggestedQuestions: rule.suggestions,
      };
    }
  }

  return {
    moduleKey: "core",
    pagePath,
    suggestedQuestions: DEFAULT_SUGGESTIONS,
  };
}
