/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')

const isProduction = process.env.NODE_ENV === 'production'

module.exports = {
    mode: isProduction ? 'production' : 'development',
    devtool: isProduction ? false : 'source-map',

    entry: {
        'federatedtalklink-main': path.join(__dirname, 'src', 'main.js'),
        'federatedtalklink-admin-settings': path.join(__dirname, 'src', 'admin-settings.js'),
        'federatedtalklink-talk-integration': path.join(__dirname, 'src', 'talk-integration.js'),
    },

    output: {
        path: path.resolve(__dirname, 'js'),
        filename: '[name].js',
        chunkFilename: '[name]-[contenthash].js',
        clean: false,
    },

    module: {
        rules: [
            {
                test: /\.vue$/,
                loader: 'vue-loader',
            },
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env'],
                    },
                },
            },
            {
                test: /\.css$/,
                use: ['style-loader', 'css-loader'],
            },
            {
                test: /\.scss$/,
                use: [
                    'style-loader',
                    'css-loader',
                    {
                        loader: 'sass-loader',
                        options: {
                            api: 'legacy',
                        },
                    },
                ],
            },
            {
                test: /\.(png|jpe?g|gif|svg|woff2?|eot|ttf|otf)$/i,
                type: 'asset/resource',
            },
        ],
    },

    plugins: [
        new VueLoaderPlugin(),
    ],

    resolve: {
        extensions: ['.js', '.vue', '.json'],
        alias: {
            vue$: 'vue/dist/vue.esm.js',
        },
        fallback: {
            path: require.resolve('path-browserify'),
            string_decoder: require.resolve('string_decoder/'),
            buffer: require.resolve('buffer/'),
        },
    },

    optimization: {
        splitChunks: {
            cacheGroups: {
                vendors: {
                    test: /[\\/]node_modules[\\/]/,
                    name: 'federatedtalklink-vendors',
                    chunks: 'all',
                    priority: -10,
                },
            },
        },
    },

    performance: {
        hints: isProduction ? 'warning' : false,
        maxAssetSize: 512000,
        maxEntrypointSize: 512000,
    },
}
