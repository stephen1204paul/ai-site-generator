const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
  ...defaultConfig,

  // Entry points for all React components
  entry: {
    // Main plugin entry
    'index': path.resolve(__dirname, 'src', 'index.js'),

    // Admin interface components
    'admin/index': path.resolve(__dirname, 'src', 'admin', 'index.js'),
    'admin/dashboard': path.resolve(__dirname, 'src', 'admin', 'dashboard.js'),
    'admin/settings': path.resolve(__dirname, 'src', 'admin', 'settings.js'),
    'admin/wizard': path.resolve(__dirname, 'src', 'admin', 'wizard.js'),
    'admin/ai-chat': path.resolve(__dirname, 'src', 'admin', 'ai-chat.js'),
    'admin/template-manager': path.resolve(__dirname, 'src', 'admin', 'template-manager.js'),

    // Block editor components
    'blocks/index': path.resolve(__dirname, 'src', 'blocks', 'index.js'),
    'blocks/hero': path.resolve(__dirname, 'src', 'blocks', 'hero', 'index.js'),
    'blocks/features': path.resolve(__dirname, 'src', 'blocks', 'features', 'index.js'),
    'blocks/testimonials': path.resolve(__dirname, 'src', 'blocks', 'testimonials', 'index.js'),
    'blocks/pricing': path.resolve(__dirname, 'src', 'blocks', 'pricing', 'index.js'),
    'blocks/cta': path.resolve(__dirname, 'src', 'blocks', 'cta', 'index.js'),
    'blocks/team': path.resolve(__dirname, 'src', 'blocks', 'team', 'index.js'),
    'blocks/faq': path.resolve(__dirname, 'src', 'blocks', 'faq', 'index.js'),

    // Gutenberg sidebar components
    'sidebar/index': path.resolve(__dirname, 'src', 'sidebar', 'index.js'),

    // Frontend scripts
    'frontend/index': path.resolve(__dirname, 'src', 'frontend', 'index.js'),
    'frontend/chat-widget': path.resolve(__dirname, 'src', 'frontend', 'chat-widget.js'),
  },

  // Output configuration
  output: {
    path: path.resolve(__dirname, 'build'),
    filename: '[name].js',
    clean: true,
  },

  // Module rules
  module: {
    ...defaultConfig.module,
    rules: [
      // JavaScript/JSX files
      {
        test: /\.(js|jsx|ts|tsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              '@babel/preset-env',
              ['@babel/preset-react', { runtime: 'automatic' }],
              '@babel/preset-typescript',
            ],
            plugins: [
              '@babel/plugin-proposal-class-properties',
              '@babel/plugin-transform-runtime',
            ],
          },
        },
      },

      // CSS files
      {
        test: /\.css$/,
        use: [
          isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
          {
            loader: 'css-loader',
            options: {
              sourceMap: !isProduction,
              modules: {
                auto: true,
                localIdentName: isProduction
                  ? '[hash:base64:5]'
                  : '[name]__[local]--[hash:base64:5]',
              },
            },
          },
          {
            loader: 'postcss-loader',
            options: {
              sourceMap: !isProduction,
              postcssOptions: {
                plugins: [
                  'autoprefixer',
                  ['postcss-preset-env', { stage: 3 }],
                ],
              },
            },
          },
        ],
      },

      // SCSS/Sass files
      {
        test: /\.(scss|sass)$/,
        use: [
          isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
          {
            loader: 'css-loader',
            options: {
              sourceMap: !isProduction,
              modules: {
                auto: true,
                localIdentName: isProduction
                  ? '[hash:base64:5]'
                  : '[name]__[local]--[hash:base64:5]',
              },
            },
          },
          {
            loader: 'postcss-loader',
            options: {
              sourceMap: !isProduction,
              postcssOptions: {
                plugins: [
                  'autoprefixer',
                  ['postcss-preset-env', { stage: 3 }],
                ],
              },
            },
          },
          {
            loader: 'sass-loader',
            options: {
              sourceMap: !isProduction,
              sassOptions: {
                outputStyle: isProduction ? 'compressed' : 'expanded',
              },
            },
          },
        ],
      },

      // Images
      {
        test: /\.(png|jpe?g|gif|svg|webp)$/i,
        type: 'asset',
        generator: {
          filename: 'images/[name].[hash][ext]',
        },
      },

      // Fonts
      {
        test: /\.(woff|woff2|eot|ttf|otf)$/i,
        type: 'asset/resource',
        generator: {
          filename: 'fonts/[name].[hash][ext]',
        },
      },
    ],
  },

  // Resolve extensions
  resolve: {
    ...defaultConfig.resolve,
    extensions: ['.tsx', '.ts', '.jsx', '.js', '.json'],
    alias: {
      '@': path.resolve(__dirname, 'src'),
      '@admin': path.resolve(__dirname, 'src/admin'),
      '@blocks': path.resolve(__dirname, 'src/blocks'),
      '@components': path.resolve(__dirname, 'src/components'),
      '@utils': path.resolve(__dirname, 'src/utils'),
      '@hooks': path.resolve(__dirname, 'src/hooks'),
      '@api': path.resolve(__dirname, 'src/api'),
      '@assets': path.resolve(__dirname, 'src/assets'),
      '@styles': path.resolve(__dirname, 'src/styles'),
    },
  },

  // Plugins
  plugins: [
    // Clean build directory
    new CleanWebpackPlugin({
      cleanOnceBeforeBuildPatterns: ['**/*', '!index.php', '!.gitkeep'],
    }),

    // Extract CSS files
    new MiniCssExtractPlugin({
      filename: '[name].css',
      chunkFilename: '[id].css',
    }),

    // WordPress dependency extraction
    new DependencyExtractionWebpackPlugin({
      injectPolyfill: true,
      combineAssets: false,
    }),

    // Copy static files
    new CopyWebpackPlugin({
      patterns: [
        {
          from: 'src/assets/images',
          to: 'images',
          noErrorOnMissing: true,
        },
        {
          from: 'src/assets/fonts',
          to: 'fonts',
          noErrorOnMissing: true,
        },
      ],
    }),

    // Additional plugins from default config
    ...defaultConfig.plugins.filter(
      (plugin) =>
        plugin.constructor.name !== 'CleanWebpackPlugin' &&
        plugin.constructor.name !== 'MiniCssExtractPlugin' &&
        plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
    ),
  ],

  // Optimization
  optimization: {
    minimize: isProduction,
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          compress: {
            drop_console: isProduction,
            drop_debugger: isProduction,
          },
          format: {
            comments: false,
          },
        },
        extractComments: false,
      }),
      new CssMinimizerPlugin({
        minimizerOptions: {
          preset: [
            'default',
            {
              discardComments: { removeAll: true },
            },
          ],
        },
      }),
    ],
    splitChunks: {
      chunks: 'all',
      cacheGroups: {
        vendor: {
          test: /[\\/]node_modules[\\/]/,
          name: 'vendor',
          priority: 10,
          reuseExistingChunk: true,
        },
        wordpress: {
          test: /[\\/]node_modules[\\/]@wordpress[\\/]/,
          name: 'wordpress',
          priority: 20,
          reuseExistingChunk: true,
        },
        common: {
          minChunks: 2,
          priority: -10,
          reuseExistingChunk: true,
        },
      },
    },
  },

  // Development server
  devServer: {
    ...defaultConfig.devServer,
    hot: true,
    liveReload: true,
    port: 8080,
    allowedHosts: 'all',
    headers: {
      'Access-Control-Allow-Origin': '*',
    },
    devMiddleware: {
      writeToDisk: true,
    },
  },

  // Source maps
  devtool: isProduction ? 'source-map' : 'eval-source-map',

  // Performance hints
  performance: {
    hints: isProduction ? 'warning' : false,
    maxAssetSize: 500000,
    maxEntrypointSize: 500000,
  },

  // Stats output
  stats: {
    all: false,
    errors: true,
    warnings: true,
    colors: true,
    assets: true,
    chunks: isProduction,
    timings: true,
  },
};