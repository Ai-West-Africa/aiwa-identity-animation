/**
 * AiWA Identity Animation – Block Registration (Editor)
 *
 * Registers the block with edit/save functions and imports the shared
 * frontend stylesheet so the editor preview matches the frontend render.
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';

import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );
