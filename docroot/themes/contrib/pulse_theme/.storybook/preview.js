import '../components/00-base/global/global.css';

const preview = {
  parameters: {
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    options: {
      storySort: (a, b) => {
        return a.title.localeCompare(b.title);
      },
    },
  },
};

export default preview;
