<?php

class RelationshipFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
	}


	/**
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	public function get_field_type(): string {
		return 'relationship';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'AcfContentNodeConnection';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'OBJECT';
	}

	/**
	 * @return array
	 */
	public function get_clone_value_to_save(): array {
		return [ $this->published_post->ID ];
	}

	/**
	 * @return string
	 */
	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestRelationship {
			  nodes {
			     __typename
			     databaseId
			  }
			}
		}
		';
	}

	/**
	 * @return array
	 */
	public function get_expected_clone_value(): array {
		return [
			'nodes' => [
				[
					'__typename' => 'Post',
					'databaseId' => $this->published_post->ID,
				]
			]
		];
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testRelationship {
			  nodes {
			     __typename
			     databaseId
			  }
			}
		}
		';
	}

	public function get_block_data_to_store() {
		return [ $this->published_post->ID ];
	}

	public function get_expected_block_fragment_response() {
		return [
			'nodes' => [
				[
					'__typename' => 'Post',
					'databaseId' => $this->published_post->ID,
				]
			]
		];
	}
}
