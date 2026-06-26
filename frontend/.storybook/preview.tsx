import '../src/index.css'
import '../src/styles/tokens.css'
import type { Preview } from "@storybook/react-vite";

const preview: Preview = {
    parameters: {
        controls: {
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },

        a11y: {
            // 'todo' - show a11y violations in the test UI only
            // 'error' - fail CI on a11y violations
            // 'off' - skip a11y checks entirely
            // Story 1.0 Task 4: fail on violations. The authoritative CI gate is the
            // jsdom axe run in src/tests/a11y.test.tsx (green "Frontend" lane); this
            // makes the Storybook browser path fail too, wherever it is run.
            test: "error",
        },
    },
};

export default preview;
