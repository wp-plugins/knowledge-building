/* Knowledge Building Map View */

/* Start the module */
(function($) {

	var Canvas, ViewPort;
	/* Working values: Repulse 15000, Attract 0.001 */
	var C = { Radius: 14, Stroke: 8, Repulse: 20000, Attract: 0.0007, Ignore_distance: 800 }
	var mouseDown = false;
	var mousePos;
	var Nodes = [];
	var NodesWaiting = [];
	var panMovement = new Vector(0, 0);
	var requestPositionsCalculating = false;
	var calculating = false;
	var Widht, Height;
	var OriginalViewPort = { width: 0, height: 0 }
	var AnimateNodeMovement = false;
	var submitted = false;

	var POST;
	
	/* These values should be fetched from the server */
	var Colors = {
		'problem': '#fcfc43',
		'my_expl': '#4ace93',
		'sci_expl': '#ffb42b',
		'evaluation': '#7e3cff',
		'summary': '#99d51a'
	};

	/* Run when document is ready */
	function Init() {
		//Get post ID
		POST = $('#post-id').val();
		
		// Initialize SVG Canvas
		Width = $(window).width();
		Height = $(window).height();
		Canvas = Raphael('raven', Width, Height);
		Canvas.setViewBox(0, 0, Width, Height);
		ViewPort = Canvas.setViewBox(0, 0, Width, Height);
		ViewPort.X = 0;
		ViewPort.Y = 0;
		OriginalViewPort = { x: ViewPort.X, y: ViewPort.Y, width: ViewPort.width, height: ViewPort.height }
		
		//Add the first element
		var main = new Node({
			id: 0, 
			position: new Vector(Width/2, Height/2), 
			radius: C.Radius * 1.5, 
			parent: false, 
			content: $('#data').attr('data-content'),
			level: 0,
			avatar: $('#data').attr('data-avatar'),
			username: $('#data').attr('data-username'),
			date: $('#data').attr('data-date')
			});
		
		//And make its position static
		main.Static = true;
		
		//Loop through the list
		IterateChildren($('#data'), 1, main);
		
		//Pan and Zoom mouse functionality
		$('svg').
			mousedown(function(e) { mouseDown = true; mousePos = { x: e.pageX, y: e.pageY }; $('#message').hide(0); }).
			mouseup(function() { mouseDown = false; MoveCanvas(); }).mouseleave(function() { mouseDown = false; MoveCanvas(); }).
			mousemove(function(e) {
				if(mouseDown) {
					Pan(e.pageX - mousePos.x, e.pageY - mousePos.y);
					panMovement = new Vector(e.pageX - mousePos.x, e.pageY - mousePos.y);
				}
				mousePos = { x: e.pageX, y: e.pageY };
			}).
			mousewheel(function(e, delta) { e.preventDefault(); $('#zoom').slider('value', $('#zoom').slider('value') + delta * $('#zoom').slider('option', 'step')); });
			
		
		/* Move connection lines before circles, so circles won't be under the lines. (Mouse events) */
		$('#raven > svg > path').insertBefore('#raven > svg > circle:first');
		
		
		
		$('#reply').click(function() {
			var v = Vector.Add(new Vector(SelectedNode.SVG.Circle.attr('cx'), SelectedNode.SVG.Circle.attr('cy')), Vector.Diag(60, Math.random() * 360));
			AddNode(new Node({
						position: new Vector(v.X, v.Y),
						radius: C.Radius, 
						parent: SelectedNode, 
						content: SelectedNode.Text,
						level: SelectedNode.level + 1
					}));
		});
		$('#close').click(function() { $('#message').hide(200); });
		
		// Reply click events
		$('#submit-reply').click(Reply);
		$('#open-reply').click(ToggleReply);
		
		// Set up canvas zooming and panning event listeners
		InitNavigation();
		
		// Node calculations
		totalStart = Date.now();
		
		// Adding a new node triggers the node calculations
		AddNode(main);
	}
	
	var NavigationButtons = { Left: false, Right: false, Up: false, Down: false };
	var PanInterval;

	function panClick() {
		var movement = new Vector(0, 0);
		if(NavigationButtons.Left)
			movement.Add(new Vector(20, 0));
		if(NavigationButtons.Right)
			movement.Add(new Vector(-20, 0));
		if(NavigationButtons.Up)
			movement.Add(new Vector(0, 20));
		if(NavigationButtons.Down)
			movement.Add(new Vector(0, -20));
		
		Pan(movement.X, movement.Y);
	}

	function panRelease() { 
		if(true || PanInterval)
			clearInterval(PanInterval);
	}

	function AddNode(node) {
		NodesWaiting.push(node);
		tries = 0;
		if(!calculating)
			CalculatePositions();
		else
			requestPositionsCalculating = true;
	}

	function IterateChildren(list, level, parent) {
		if(list.is('ul')) {
			list.children('li').each(function(i) {
				var childCount = list.children('li').size();
				if(parent.parent)
					var angle = Vector.Angle(parent.position, parent.parent.position) + 180;
				else
					var angle = 360/childCount * i;
				var pos = Vector.Diag(30, (15/childCount) * i - 15/2 + angle);
				pos.Add(parent.position);
				var main = new Node({
						id: $(this).attr('data-id'), 
						position: new Vector(pos.X, pos.Y), 
						radius: C.Radius, 
						parent: parent, 
						content: $(this).attr('data-content'), 
						type: $(this).attr('data-kbtype'),
						typeName: $(this).attr('data-kbname'),
						avatar: $(this).attr('data-avatar'),
						username: $(this).attr('data-username'),
						date: $(this).attr('data-date'),
						additionalParents: $(this).attr('data-additional-parents'),
						level: level
					});

				Nodes.push(main);
				$(this).children('ul').each(function() { IterateChildren($(this), level + 1, main); });
			});
		}
	}

	var tries = 0; 
	var totalStart = 0;
	var rounds = 0;
	function CalculatePositions() {
		
		// Add nodes that are waiting to be added
		// Can't add in the middle of a loop
		for(var i = 0; i < NodesWaiting.length; i++)
			Nodes.push(NodesWaiting[i]);
			
		//var nds = [];
		//for(var i = 0; i < Nodes.length; i++)
		//	nds.push({ id: Nodes[i].ID, pos: Nodes[i].position, parent: Nodes[i].parent.ID, static: Nodes[i].Static });
		
		//$.post('positions.php', { nodes: JSON.stringify(nds) }, function(response) { console.log(response); });
		//return;
		
		// Move connections under circles.
		if(NodesWaiting.length > 0)
			$('#raven > svg > path').insertBefore('#raven > svg > circle:first');
		
		NodesWaiting = [];
		requestPositionsCalculating = false;
		calculating = true;
		
		var moved = false;
		var repulsive = new Vector();
		var attractive = new Vector();
		for(var j = 0; j < Nodes.length; j++) {
			var jNode = Nodes[j];
			
			if(jNode.Static)
				continue;
			
			repulsive.X = 0; repulsive.Y = 0;
			attractive.X = 0; attractive.Y = 0;
			
			for(var i = 0; i < Nodes.length; i++) {
				var iNode = Nodes[i];
				
				//Obviously we don't have to calculate forces "between" the same node.
				if(i == j) continue;
				
				//If the node is the another node's child calculate attractive force (child pulls its parent)
				if(iNode.parent && iNode.parent === jNode) {
					attractive.Add(AttractiveMovement(jNode, iNode));
				}
				
				//Repulsive force
				repulsive.Add(RepulsiveMovement(iNode, jNode));
			
			}
			// Node's parent pulls the node
			if(jNode.parent){
				attractive.Add(AttractiveMovement(jNode, jNode.parent));
			}
			
			// The total forces
			var v = Vector.Add(attractive, repulsive);
			var len = v.LengthSquared();
			// Limit the maximum movement (otherwise causes bugs)
			if(len > 150 * 150) 
				v.Clamp(15);
			
			if(len > 0.3) { //If movement is small enough, no need to calculate forces again
				moved = true;
			
				// Apply movement
				// Second attribute is to how often the nodes will be drawn (true = draw, false = don't draw)
				jNode.Move(v, false);
			}
		}
		
		for(i = 0; i < Nodes.length; i++) {
			Nodes[i].UpdatePosition();
		}
		
		tries++;
		if((moved && tries < 1000) || requestPositionsCalculating)
			setTimeout(CalculatePositions, 10);
		else {
			calculating = false;
			
			//for(var i = 0; i < Nodes.length; i++) 
				//Nodes[i].UpdatePosition();
				
			$('#fps').text(Date.now() - totalStart);
		}
		rounds++;
	}

	function RepulsiveMovement(node1, node2) {
		
		var dist = Vector.DistanceSquared(node1.position, node2.position);
		if(dist > C.Ignore_distance * C.Ignore_distance) return new Vector(0, 0);
		
		var v = Vector.Subtract(node1.position, node2.position);
		v.Normalize();
		
		if(v.LengthSquared() == 0) {
			v.Add(Vector.Diag(10, Math.random() * 360));
			dist = v.LengthSquared();
		}
		v.Multiply(-C.Repulse / dist);
		
		return v;
	}

	function AttractiveMovement(node1, node2) {
		
		// Attractive force
		var v = Vector.Subtract(node1.position, node2.position);
		v.Normalize();
		var dist = Vector.DistanceSquared(node1.position, node2.position);
		
		if(dist === 0) {
			v.Add(Vector.Diag(2, Math.random() * 360));
			dist = v.LengthSquared();
		}
		
		v.Multiply(-C.Attract * dist);
		return v;
	}
	var SelectedNode;
	function Node(args) {
		if(!args.typeName)
			args.typeName = 'Unspecified';
		
		this.OldPosition;
		this.Avatar = args.avatar;
		this.Username = args.username;
		this.ID = args.id;
		this.Date = args.date;
		this.parent = args.parent;
		this.position = args.position;
		this.Radius = args.radius;
		this.Color = Colors[args.type];
		this.TypeName = args.typeName;
		this.Content = args.content;
		this.level = args.level;
		
		
		this.Parents = [args.parent];
		//if(args.additionalParents)
		//this.Parents.concat(args.additionalParents.split(','));
		
		this.SVG = Node.Add(this.position.X, this.position.Y, this.Radius, this.Parents, this.Color);
		var node = this;
		
		
		this.SVG.Circle.click(function(e) {
				//Shrink the old selected node to its normal size
				if(SelectedNode)
					SelectedNode.SVG.Circle.animate({ r: C.Radius }, 200);
				
				
				//Select the selected node
				SelectedNode = node;
				SelectedNode.SVG.Circle.animate({ r: C.Radius * 1.5 }, 200);
			
				$('#parent-comment-id').val(SelectedNode.ID);
			
				//Set message data
				var msg = $('#message');
				msg.hide().find('.message-content').text(node.Content);
				msg.find('.message-type').html(node.TypeName);
				msg.find('.message-avatar').css({ backgroundImage: 'url(' + node.Avatar + ')' });
				msg.find('.message-username').text(node.Username);
				msg.find('.message-coords').text();
				msg.find('.message-date').text(_dateformat(node.Date));
				console.log(node.Date);
				function _dateformat(d) { d = new Date(d); return d.getHours() + ':' + d.getMinutes() + ' ' + d.getDate() + '.' + (d.getMonth()+1) + '.' + d.getFullYear(); }
				
				//Get SVG element position relative to HTML document
				var bounds = node.SVG.Circle.node.getBoundingClientRect();
				
				//Position the message box
				var top, left;
				if(bounds.left < Width/ 2)
					left = bounds.right + 10; 
				else
					left = bounds.left - msg.width() - 10;
					
			
				top = bounds.top + 10 - msg.height();
					
				if(left + msg.width() > Width)
					left = Width - 50 - msg.width();
				if(left < 0)
					left = 10;
				if(top + msg.height() > Height)
					top = Height - 50 - msg.height();
				if(top < 0)
					top = 10;
					
				msg.css({ top: top, left: left });
				
				//Self-explanatory 
				//msg.css({ borderColor: node.Color });
				
				console.log(node.Color);
				/*
				if(((Red value X 299) + (Green value X 587) + (Blue value X 114)) / 1000 > 125) {
					
				}
				*/
				msg.find('.message-header').css({ backgroundColor: node.Color });
				
				
				msg.show(200);
			});
		
		
		this.Move = function(movement, update) {
			this.position.X += movement.X;
			this.position.Y += movement.Y;
			
			if(update)
				this.UpdatePosition();
		}
		this.UpdateConnections = function() {
			
			if(this.SVG.Connection) {
				
				for(var i = 0; i < this.Parents.length; i++) {
					if(AnimateNodeMovement) 
						this.SVG.Connection.animate({ path:'M' + this.position.X + ',' + this.position.Y + 'L' + this.parent.position.X + ',' + this.parent.position.Y }, 200);
					else
						this.SVG.Connection.attr({ path:'M' + this.position.X + ',' + this.position.Y + 'L' + this.parent.position.X + ',' + this.parent.position.Y });
				}
			}
		}
		this.UpdatePosition = function() {
			if(AnimateNodeMovement)
				this.SVG.Circle.animate({ cx: this.position.X, cy: this.position.Y }, 200);
			else
				this.SVG.Circle.attr({ cx: this.position.X, cy: this.position.Y });
			
			
			this.UpdateConnections();
		}
		
		this.SetAvatar = function() {
			this.SVG.Circle.animate({ 'fill':'url(url)' }, 1);
		}
		//this.SetAvatar();
	}

	Node.Add = function(x, y, Radius, parents, color) {
		var ret = {};
		
		var circle = Canvas.circle(x, y, Radius);
		circle.attr('fill', 'rgba(240, 240, 240, 1)');
		circle.attr('stroke', color);
		circle.attr('stroke-width', C.Stroke);
		ret.Circle = circle;
		
		//circle.mouseover(function() { this.animate({ r: Radius * 1.2 }, 300, '<'); }).
		//mouseout(function() { this.animate({ r: Radius }, 300); });
		
		if(parents) {
			ret.Connections = [];
			console.log(parents);
			for(i = 0; i < parents.length; i++) {
				if(!parents[i]) continue;
				
				var path = Canvas.path('M' + x + ',' + y + 'L' + parents[i].position.X + ',' + parents[i].position.Y);
				path.attr('stroke', 'rgba(240, 240, 240, 0.9)');
				ret.Connections.push(path);
			}
		}
		return ret;
	}

	

	function Pan(x, y) {
		ViewPort.X -= x * scale;
		ViewPort.Y -= y * scale;
		Canvas.setViewBox(ViewPort.X, ViewPort.Y, ViewPort.width, ViewPort.height);
	}

	function ResetCanvasPosition() {
		var x = -(OriginalViewPort.width * scale - OriginalViewPort.width) / 2;
		var y = -(OriginalViewPort.height * scale - OriginalViewPort.height) / 2;
		
		function anim() {
			ViewPort.X = ViewPort.X - (ViewPort.X - x) * 0.5;
			ViewPort.Y = ViewPort.Y - (ViewPort.Y - y) * 0.5;
			Canvas.setViewBox(ViewPort.X, ViewPort.Y, ViewPort.width, ViewPort.height);
			
			if(Math.abs(ViewPort.X - x) < 10) {
				ViewPort.X = x;
				ViewPort.Y = y;
			}
			else
				setTimeout(anim, 16);
		}
		setTimeout(anim, 16);
	}

	function MoveCanvas() {
		panMovement.Multiply(0.85);
		Pan(panMovement.X, panMovement.Y);
		
		if(panMovement.LengthSquared() > 10 && !mouseDown)
			setTimeout(MoveCanvas, 16);
	}

	var scale = 1;
	function Zoom(z) {
		scale = z;
		
		if(scale < 0.25) {
			scale = 0.25;
		}
		if(scale > 4) {
			scale = 4;
		}
		
		ViewPort.X -= (OriginalViewPort.width * scale - ViewPort.width) / 2;
		ViewPort.Y -= (OriginalViewPort.height * scale - ViewPort.height) / 2;
		
		ViewPort.width = scale * OriginalViewPort.width;
		ViewPort.height = scale * OriginalViewPort.height;
		
		Canvas.setViewBox(ViewPort.X, ViewPort.Y, ViewPort.width, ViewPort.height);
		
	}

	function ToggleReply() { $('#reply-wrapper').toggle(200); }

	function Reply() {
		if(submitted)
			return;
		
		submitted = true;
		var url = $('#admin-ajax-url').val();
		var typename = $('select[name="knbu_type"] option[value="'+ $('select[name="knbu_type"]:first').val() + '"]').text();
		$.post(url, { 
			comment_post_ID: POST,
			comment_knbu_type: $('select[name="knbu_type"]:first').val(),
			comment_content: $('textarea[name="comment-content"]:first').val(),
			comment_parent: $('#parent-comment-id').val(),
			action: 'knbu_new_reply'
			}, function(response) {
				//console.log(response);
				/* Response data comes in JSON format */
				response = JSON.parse(response);
				
				if(response.Success) {
					/* Add new node */
					/* At this point the comment has been saved to the server */
					var n = new Node({
						id: response.id, 
						position: new Vector(
							SelectedNode.position.X + Math.random() * 50, 
							SelectedNode.position.Y + Math.random() * 50), 
						radius: C.Radius, 
						parent: SelectedNode, 
						content: response.content, 
						type: response.knbu,
						typeName: typename,
						avatar: response.avatar,
						username: response.username,
						level: SelectedNode.level + 1,
						date: response.date
					});
					AddNode(n);
					
					/* Reset reply form */
					$('#message').hide(200, function() {
						$('select[name="knbu_type"]:first option:first').attr('selected', true);
						$('textarea[name="comment-content"]:first').val('');
						ToggleReply();
						submitted = false;
					});
				}
				else if(response.Message)
					alert(response.Message);
				else
					console.log('General error message');
		});
	}

	function InitNavigation() {
		$('#zoom').slider({
				orientation: 'vertical',
				change: function(e, ui) { Zoom(ui.value); },
				slide: function(e, ui) {
					Zoom(ui.value);
				},
				max: 4,
				min: 0.25,
				step: 0.1,
				value: 1
			});
			
		
		PanInterval = setInterval(panClick, 30);
		$('#pan .left').mousedown(function() { NavigationButtons.Left = true; });
		$('#pan .right').mousedown(function() { NavigationButtons.Right = true; });
		$('#pan .up').mousedown(function() { NavigationButtons.Up = true; });
		$('#pan .down').mousedown(function() { NavigationButtons.Down = true; });
		$('#pan .center').mousedown(ResetCanvasPosition);
		$(window).mouseup(function() { NavigationButtons = {} }).mouseleave(function() { NavigationButtons = {} });
	}
	
	function Vector(x, y) {
		this.X = x; 
		this.Y = y;
		
		this.Length = function() { return Math.pow(Math.pow(this.X, 2) + Math.pow(this.Y, 2), 1/2); }
		this.LengthSquared = function() { return Math.pow(this.X, 2) + Math.pow(this.Y, 2); }
		this.Add = function(v2) { this.X += v2.X; this.Y += v2.Y; }
		this.Multiply = function(k) { this.X *= k; this.Y *= k; }
		this.Divide = function(k) { this.X /= k; this.Y /= k; }
		this.Normalize = function() { if(!(this.X == 0 && this.Y == 0)) { var len = this.Length();  this.X /= len; this.Y /= len; } }
		this.Clamp = function(m) { if(this.LengthSquared() > m * m) { this.Normalize(); this.Multiply(m); } }
		Vector.Angle = function(v1, v2) { return Math.atan2(v2.Y - v1.Y, v2.X - v1.X) * 180/Math.PI; }
		Vector.Add = function(v1, v2) { return new Vector(v1.X + v2.X, v1.Y + v2.Y); }
		Vector.Subtract = function(v1, v2) { return new Vector(v1.X - v2.X, v1.Y - v2.Y); }
		Vector.Multiply = function(v1, k) { return new Vector(v1.X * k, v1.Y * k); }
		Vector.Clamp = function(v, max) { 
			if(v.LengthSquared() > max * max) { v.Normalize(); v.Multiply(max); }
			return v;
		} 
		Vector.Diag = function(s, a) { return new Vector(s * Math.cos(a * Math.PI/180), s * Math.sin(a * Math.PI/180)); }
		Vector.DistanceSquared = function(v1, v2) {
			return Math.pow(v2.X - v1.X, 2) + Math.pow(v2.Y - v1.Y, 2);
		}
		Vector.Distance = function(v1, v2) { return Math.pow(Vector.DistanceSquared(v1, v2), 1/2); }
	}
	
	/* Initialize module when document is ready */
	$(function() { Init(); });
	
}(jQuery));