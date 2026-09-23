// True web-safe font stacks only - real Google Fonts/webfonts aren't
// reliably loaded across mail clients (Outlook desktop in particular
// ignores @font-face entirely), so the block editor never offers one.
export const WEB_SAFE_FONTS = [
  { label: 'Arial', value: 'Arial, Helvetica, sans-serif' },
  { label: 'Helvetica', value: 'Helvetica, Arial, sans-serif' },
  { label: 'Georgia', value: 'Georgia, "Times New Roman", serif' },
  { label: 'Times New Roman', value: '"Times New Roman", Times, serif' },
  { label: 'Verdana', value: 'Verdana, Geneva, sans-serif' },
  { label: 'Trebuchet MS', value: '"Trebuchet MS", Helvetica, sans-serif' },
  { label: 'Courier New', value: '"Courier New", Courier, monospace' },
];

export const DEFAULT_FONT = WEB_SAFE_FONTS[0].value;
