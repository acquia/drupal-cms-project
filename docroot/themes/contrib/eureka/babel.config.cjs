module.exports = (api) => {
  api.cache(true);

  const presets = [
    [
      '@babel/preset-env',
      {
        corejs: 3,
        useBuiltIns: 'usage',
      },
    ],
  ];

  const comments = false;

  const targets = [
    'chrome >0 and last 2.5 years',
    'edge >0 and last 2.5 years',
    'safari >0 and last 2.5 years',
    'firefox >0 and last 2.5 years',
    'and_chr >0 and last 2.5 years',
    'and_ff >0 and last 2.5 years',
    'ios >0 and last 2.5 years',
  ];
  return { presets, comments, targets  };
};
