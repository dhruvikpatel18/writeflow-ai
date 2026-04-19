describe( 'Dynamic Block view', () => {
	it( 'loads without errors', () => {
		const consoleSpy = jest.spyOn( console, 'log' ).mockImplementation();

		jest.isolateModules( () => {
			require( '../view' );
		} );

		expect( consoleSpy ).toHaveBeenCalledWith(
			'Hello World! (from writeflow-ai-dynamic-block block)'
		);

		consoleSpy.mockRestore();
	} );
} );
