#!/usr/bin/env node

/**
 * fbx_to_glb_converter.js
 * 
 * A Node.js script to convert all .fbx files in a specified directory to .glb files.
 * 
 * Dependencies:
 * - fbx2gltf: Converts .fbx to .glb
 * - fs-extra: Extended file system methods
 * - path: File path utilities
 * 
 * Usage:
 *   node fbx_to_glb_converter.js [source_directory] [output_directory]
 * 
 * If directories are not provided, defaults will be used.
 */

const fs = require('fs-extra');
const path = require('path');
const fbx2gltf = require('fbx2gltf');

// Configuration: Default directories.
// Install root as the PHP side resolves it, minus the steps node has no
// way to reach: SC_WEB_ROOT from the environment, else /web.
const SC_WEB_ROOT = process.env.SC_WEB_ROOT || "/web";
const DEFAULT_SOURCE_DIR = `${SC_WEB_ROOT}/gebarenoverleg_media/vicon/`;
const DEFAULT_OUTPUT_DIR = `${SC_WEB_ROOT}/gebarenoverleg_media/vicon/`;

/**
 * Converts an FBX file to GLB using fbx2gltf.
 * @param {string} fbxPath - Path to the .fbx file.
 * @param {string} glbPath - Path to output the .glb file.
 * @returns {Promise<void>}
 */
function convertFbxToGlb(fbxPath, glbPath) {
    return new Promise((resolve, reject) => {
        console.log(`Converting FBX to GLB: ${path.basename(fbxPath)}`);
        
        // Construct the command with necessary flags
        // '--binary' flag ensures the output is a .glb file
        // '--blend-shape-normals' is an example flag; add others as needed
        const options = ['--blend-shape-normals', '--blend-shape-tangents', '--embed', '--user-properties', '-v'
        ];
        
        fbx2gltf(fbxPath, glbPath, options)
            .then(() => {
                console.log(`Successfully converted to GLB: ${path.basename(glbPath)}`);
                resolve();
            })
            .catch(error => {
                console.error(`Error converting FBX to GLB for ${path.basename(fbxPath)}:`, error);
                reject(error);
            });
    });
}

/**
 * Processes a single FBX file: converts to GLB and saves the GLB.
 * @param {string} fbxFile - Path to the .fbx file.
 * @param {string} outputDir - Directory to save the .glb file.
 * @returns {Promise<void>}
 */
async function processFbxFile(fbxFile, outputDir) {
    const baseName = path.basename(fbxFile, '.fbx');
    const glbPath = path.join(outputDir, `${baseName}.glb`);

    try {
        await convertFbxToGlb(fbxFile, glbPath);
    } catch (error) {
        console.error(`Failed to process ${fbxFile}:`, error);
    }
}

/**
 * Main function to scan the source directory and process all FBX files.
 */
async function main() {
    // Parse command-line arguments for source and output directories
    const args = process.argv.slice(2);
    const sourceDir = args[0] ? path.resolve(args[0]) : DEFAULT_SOURCE_DIR;
    const outputDir = args[1] ? path.resolve(args[1]) : DEFAULT_OUTPUT_DIR;

    console.log(`Source Directory: ${sourceDir}`);
    console.log(`Output Directory: ${outputDir}`);

    // Ensure the source directory exists
    if (!fs.existsSync(sourceDir)) {
        console.error(`Source directory does not exist: ${sourceDir}`);
        process.exit(1);
    }

    // Create the output directory if it doesn't exist
    await fs.ensureDir(outputDir);

    // Read all files in the source directory
    const files = await fs.readdir(sourceDir);

    // Filter for .fbx files
    const fbxFiles = files.filter(file => path.extname(file).toLowerCase() === '.fbx');

    if (fbxFiles.length === 0) {
        console.log('No .fbx files found in the source directory.');
        process.exit(0);
    }

    console.log(`Found ${fbxFiles.length} .fbx file(s) to process.\n`);

    // Process each FBX file sequentially
    for (const file of fbxFiles) {
        const fbxPath = path.join(sourceDir, file);
        //first check if fbx file already exist
        const baseName = path.basename(fbxPath, '.fbx');
        const glbPath = path.join(outputDir, `${baseName}.glb`);
        if (fs.existsSync(glbPath)) {
            console.log(`GLB file already exists: ${glbPath}`);
            continue;
        }
        //convert max 10mb only
        const stats = fs.statSync(fbxPath);
        const fileSizeInBytes = stats.size;
        const fileSizeInMegabytes = fileSizeInBytes / (1024*1024);
        if (fileSizeInMegabytes > 10) {
            console.log(`File size is more than 10mb: ${fileSizeInMegabytes}mb`);
            continue;
        }
        await processFbxFile(fbxPath, outputDir);
    }

    console.log('\nAll files have been processed.');
}

// Execute the main function
main().catch(error => {
    console.error('An unexpected error occurred:', error);
    process.exit(1);
});
