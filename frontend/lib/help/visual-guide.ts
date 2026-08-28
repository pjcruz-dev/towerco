export type VisualCallout = {
  n: number;
  title: string;
  body: string;
  /** Percent from left / top of the screenshot (0–100). */
  x: number;
  y: number;
};

export type VisualGuideSection = {
  id: string;
  title: string;
  description: string;
  imageSrc: string;
  imageAlt: string;
  callouts: VisualCallout[];
  tip?: string;
};

export type VisualGuideTab = {
  id: string;
  label: string;
  sections: VisualGuideSection[];
};
