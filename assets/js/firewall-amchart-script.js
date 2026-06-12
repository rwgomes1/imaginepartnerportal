// Prepare the data from PHP for amCharts
var countryData = <?php echo json_encode($countryData, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
// Example format: [ { "id": "US", "value": 10 }, { "id": "CN", "value": 3 }, ... ]

am5.ready(function() {

  // Create root and chart
  var root = am5.Root.new("chartdiv");
  root.setThemes([ am5themes_Animated.new(root) ]);

  // Create the map chart
  var chart = root.container.children.push(
    am5map.MapChart.new(root, {
      panX: "none",
      panY: "none",
      wheelX: "none",
      wheelY: "none",
      projection: am5map.geoMercator()
    })
  );

  // Create polygon series for the world map
  var polygonSeries = chart.series.push(
    am5map.MapPolygonSeries.new(root, {
      geoJSON: am5geodata_worldLow
    })
  );

  // Create a heat rule so that countries with higher "value" appear darker
  polygonSeries.set("heatRules", [{
    target: polygonSeries.mapPolygons.template,
    dataField: "value",
    min: am5.color(0xffdddd),
    max: am5.color(0xff0000),
    key: "fill"
  }]);

  // Load data into the polygon series
  polygonSeries.data.setAll(countryData);

  polygonSeries.mapPolygons.template.setAll({
    tooltipText: "{name}: {value}",
    interactive: true
  });

  // Animate on load
  polygonSeries.appear(1000, 100);
  chart.appear(1000, 100);

}); // end am5.ready()