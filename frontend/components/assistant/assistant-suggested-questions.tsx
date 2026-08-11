"use client";

type Props = {
  questions: string[];
  onSelect: (question: string) => void;
  disabled?: boolean;
};

export function AssistantSuggestedQuestions({ questions, onSelect, disabled }: Props) {
  if (questions.length === 0) {
    return null;
  }

  return (
    <div className="space-y-2">
      <p className="text-xs font-medium text-muted-foreground">Suggested</p>
      <div className="flex flex-col gap-1.5">
        {questions.map((question) => (
          <button
            key={question}
            type="button"
            disabled={disabled}
            onClick={() => onSelect(question)}
            className="rounded-xl bg-muted/50 px-3 py-2 text-left text-sm text-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-50"
          >
            {question}
          </button>
        ))}
      </div>
    </div>
  );
}
