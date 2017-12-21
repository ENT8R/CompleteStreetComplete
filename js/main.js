const plots = [
  'bar',
  'pie'
];

const types = [
  'chart',
  'translation'
]

function init(data) {
  for (let key in data) {

    let item = data[key];

    //Get the name, the type and all values
    let name = item.name;
    let type = item.type;
    let values = item.values;

    for (let language in values) {
      let singleValues = values[language];

      let plot;
      let x = [];
      let y = [];

      for (let answer in singleValues) {
        let amount = singleValues[answer];
        x.push(answer);
        y.push(amount);
      }

      switch (plots[Math.floor(Math.random() * plots.length)]) {
        case plots[0]:
          plot = [{
            x: x,
            y: y,
            type: 'bar'
          }];
          break;
        case plots[1]:
          plot = [{
            labels: x,
            values: y,
            type: 'pie'
          }];
          break;
      }

      let id = generateID();

      let template = $('#chart-template').html();
      Mustache.parse(template);
      let rendered = Mustache.render(template, {
        title: name + ' (' + language + ')',
        id: id
      });

      if (!$('#content').is(":visible")) $('#content').show();

      if (type == types[0]) {
        $('#evaluations').append(rendered);
      } else if (type == types[1]) {
        $('#translations').append(rendered);
      }

      //Initialize a new plot
      Plotly.newPlot(id, plot, null, {
        displayModeBar: false
      });
    }
  }
}

function generateID() {
  let S4 = function() {
    return (((1 + Math.random()) * 0x10000) | 0).toString(16).substring(1);
  };
  return (S4() + S4() + "-" + S4() + "-" + S4() + "-" + S4() + "-" + S4() + S4() + S4());
}

$.get('api/get', function(data) {
  init(data[0]);
});
