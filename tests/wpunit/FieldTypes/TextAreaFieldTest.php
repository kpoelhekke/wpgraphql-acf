<?php

/**
 * Text Area Field Test
 *
 * Tests the behavior of "text_area" field mapping to the WPGraphQL Schema
 */
class TextAreaFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function get_field_type():string {
		return "textarea";
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	/**
	 * @return mixed|string|null
	 */
	public function get_block_data_to_store() {
		return $this->get_data_to_store();
	}

	/**
	 * @return string
	 */
	public function get_acf_clone_fragment():string {
		return '
			fragment AcfTestGroupFragment on AcfTestGroup {
				clonedTestTextarea
			}
		';
	}

}
