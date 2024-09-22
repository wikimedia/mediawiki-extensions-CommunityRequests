/**
 * The wikitext template parameters (and optional page ID) of a wish
 */
class Wish {

	/**
	 * Normalize an array of values by trimming whitespace,
	 * removing empty values, and removing duplicates.
	 *
	 * @param {Array<string>} givenArray
	 * @return {Array<string>}
	 * @private
	 */
	static normalizeArray( givenArray ) {
		// Trim whitespace.
		return givenArray.map( ( value ) => value.trim() )
			// Remove empty values and duplicates.
			.filter( ( value, index, array ) => value !== '' && array.indexOf( value ) === index );
	}

	/**
	 * Get the storage value for a parameter from an array of values.
	 *
	 * @param {Array<string>} values
	 * @return {string}
	 */
	static getValueFromArray( values ) {
		return this.normalizeArray( values ).join( Wish.DELIMITER );
	}

	/**
	 * Get an array of values from a storage value for a parameter.
	 *
	 * @param {string} value
	 * @return {Array<string>}
	 */
	static getArrayFromValue( value ) {
		return this.normalizeArray( value.split( Wish.DELIMITER ) );
	}

	/**
	 * Props values here should be identical to storage, be it wikitext or MariaDB.
	 *
	 * @param {Object} props
	 */
	constructor( props ) {
		// Non-template (metadata) properties
		this.pageId = props.pageId || null;
		this.page = props.page || '';
		this.name = props.name || '';
		this.lang = props.lang || '';
		this.updated = props.updated || '';

		// Template parameters
		this.baselang = props.baselang || '';
		this.type = props.type || '';
		this.status = props.status || '';
		this.title = props.title || '';
		this.description = props.description || '';
		this.audience = props.audience || '';
		this.tasks = props.tasks || '';
		this.proposer = props.proposer || '';
		this.created = props.created || '';
		this.projects = props.projects || '';
		this.otherproject = props.otherproject || '';
		this.area = props.area || '';
	}
}

// Delimiter for array types
Wish.DELIMITER = ',';

Wish.TYPE_FEATURE = 'feature';
Wish.TYPE_BUG = 'bug';
Wish.TYPE_CHANGE = 'change';
Wish.TYPE_UNKNOWN = '';
Wish.STATUS_DRAFT = 'draft';
Wish.STATUS_SUBMITTED = 'submitted';
Wish.STATUS_OPEN = 'open';
Wish.STATUS_IN_PROGRESS = 'started';
Wish.STATUS_DELIVERED = 'delivered';
Wish.STATUS_BLOCKED = 'blocked';
Wish.STATUS_ARCHIVED = 'archived';

module.exports = Wish;
